<?php
/*
	Plugin Name: Contenido Team Manager
	Plugin URI:  http://conteni.do
	Description: Plugin that helps you manage your team, production costs, and tasks so you can create great content for your audience without exceeding your budget.
	Version:     1.1
	Author:      Angles Media Corp.
	Author URI:  http://www.conteni.do
*/


if (!class_exists('OdysseyCTM'))
{
	class OdysseyCTM
	{
		private static $Instance;


		static function Instance()
		{
			if (!self::$Instance)
			{
				self::$Instance = new self();
			}

			return self::$Instance;
		}


		function __construct()
		{
			$this->TextDomain = 'Contenido';
			$this->PluginFile = __FILE__;
			$this->PluginURL = plugin_dir_url(__FILE__);
			$this->PluginName = 'Contenido Team Manager';

			if (self::$Instance)
			{
				wp_die( sprintf( '<strong>%s:</strong> Please use the <code>%s::Instance()</code> method for initialization.', $this->PluginName, __CLASS__ ) );
			}

			$this->TasksStatusClass = array
			(
				1 => 'label-danger',
				2 => 'label-warning',
				3 => 'label-success',
			);

			$this->IdeasStatusClass = array
			(
				1 => 'label-warning',
				2 => 'label-success',
				3 => 'label-primary',
				4 => 'label-danger',
			);

			register_activation_hook($this->PluginFile, array($this, 'Activate'));
			add_filter('plugin_action_links_'.plugin_basename($this->PluginFile), array($this, 'ActionLinks'));
			add_action('init', array($this, 'Init'));
			add_action('admin_menu', array($this, 'AdminMenu'));
			add_action('wp_ajax_OCTMTaskEdit', array($this, 'AJAXTaskEdit'));
			add_action('wp_ajax_OCTMTaskEndDate', array($this, 'AJAXTaskEndDate'));
			add_action('wp_ajax_OCTMIdeaEdit', array($this, 'AJAXIdeaEdit'));
			add_action('wp_ajax_OCTMIdeaPropose', array($this, 'AJAXIdeaPropose'));
			add_action('wp_ajax_OCTMMemberEdit', array($this, 'AJAXMemberEdit'));
		}


		function Activate()
		{
			global $wpdb;

			$SQL = "
			CREATE TABLE OCTM_Ideas 
			(
				Id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				Idea VARCHAR(200) NOT NULL,
				Asset SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				Priority TINYINT UNSIGNED NOT NULL DEFAULT '0',
				Due DATE NOT NULL DEFAULT '0000-00-00',
				Proposer SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				Status TINYINT NOT NULL DEFAULT '0',
				Updated TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
				Created TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (Id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			CREATE TABLE OCTM_Tasks 
			(
				Id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				Idea INT UNSIGNED NOT NULL DEFAULT '0',
				Priority TINYINT UNSIGNED NOT NULL DEFAULT '0',
				Task VARCHAR(200) NOT NULL,
				Type SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				Member BIGINT NOT NULL DEFAULT '0',
				Estimate VARCHAR(10) NOT NULL,
				Due DATE NOT NULL DEFAULT '0000-00-00',
				Status TINYINT NOT NULL DEFAULT '0',
				Updated TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP,
				Created TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (Id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			CREATE TABLE OCTM_Meta 
			(
				Id INT UNSIGNED NOT NULL AUTO_INCREMENT,
				Name VARCHAR(200) NOT NULL,
				Value VARCHAR(200) NOT NULL,
				Type VARCHAR(200) NOT NULL,
				PRIMARY KEY  (Id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;

			INSERT INTO OCTM_Meta (Id, Name, Value, Type) VALUES (1, 'Not Started', '1', 'TaskStatus'), (2, 'In Progress', '2', 'TaskStatus'), (3, 'Done', '3', 'TaskStatus'), (4, 'Researching', '1', 'TaskType'), (5, 'Writing', '2', 'TaskType'), (6, 'Designing', '3', 'TaskType'), (7, 'Editing', '4', 'TaskType'), (8, 'Other', '5', 'TaskType'), (9, 'Blog Post', '1', 'IdeaAsset'), (10, 'Case Study', '2', 'IdeaAsset'), (11, 'Whitepaper', '3', 'IdeaAsset'), (12, 'Video', '4', 'IdeaAsset'), (13, 'Infographic', '5', 'IdeaAsset'), (14, 'Social Post', '6', 'IdeaAsset'), (15, 'Content Team', '1', 'IdeaProposer'), (16, 'Client', '2', 'IdeaProposer'), (17, 'Sales', '3', 'IdeaProposer'), (18, 'Engineering', '4', 'IdeaProposer'), (19, 'Finance', '5', 'IdeaProposer'), (20, 'Marketing', '6', 'IdeaProposer'), (21, 'Management', '7', 'IdeaProposer'), (22, 'Proposed', '1', 'IdeaStatus'), (23, 'Accepted', '2', 'IdeaStatus'), (24, 'Published', '3', 'IdeaStatus'), (25, 'Rejected', '4', 'IdeaStatus'), (26, '1', '#79d1cf', 'TaskStatusColor'), (27, '2', '#0db2ff', 'TaskStatusColor'), (28, '3', '#e67a77', 'TaskStatusColor'), (29, '1', '#e67a77', 'IdeaAssetColor'), (30, '2', '#0db2ff', 'IdeaAssetColor'), (31, '3', '#79d1cf', 'IdeaAssetColor'), (32, '4', '#ff588a', 'IdeaAssetColor'), (33, '5', '#a488d6', 'IdeaAssetColor'), (34, '6', '#fc855f', 'IdeaAssetColor'), (35, '1', '#0db2ff', 'IdeaStatusColor'), (36, '2', '#e67a77', 'IdeaStatusColor'), (37, '3', '#ff588a', 'IdeaStatusColor'), (38, '4', '#79d1cf', 'IdeaStatusColor');
			";

			require_once ABSPATH.'wp-admin/includes/upgrade.php';
			dbDelta($SQL);
		}


		function ActionLinks($Links)
		{
			$Link = '<a href="admin.php?page=Contenido">'.__('Dashboard', $this->TextDomain).'</a>';

			array_push($Links, $Link);

			return $Links;
		}


		function Init()
		{
			load_plugin_textdomain($this->TextDomain, false, dirname(plugin_basename($this->PluginFile)).'/languages/');
		}


		function LoadDBData()
		{
			global $wpdb;

			$this->Tasks = $wpdb->get_results('SELECT * FROM OCTM_Tasks ORDER BY Priority, Due');
			$this->TasksPriority = 3; // $wpdb->get_var('SELECT COUNT(Id)+1 FROM OCTM_Tasks')
			$this->TasksTypes = $this->ArrayReduce( $wpdb->get_results('SELECT Value AS Id, Name FROM OCTM_Meta WHERE Type = "TaskType" ORDER BY Value', ARRAY_A) );
			$this->TasksStatus = $this->ArrayReduce( $wpdb->get_results('SELECT Value AS Id, Name FROM OCTM_Meta WHERE Type = "TaskStatus" ORDER BY Value', ARRAY_A) );
			$this->TasksMy = $wpdb->get_var('SELECT COUNT(Id) FROM OCTM_Tasks WHERE Member = '.get_current_user_id());

			$IdeasDB = $wpdb->get_results('SELECT * FROM OCTM_Ideas ORDER BY Priority, Due');
			$this->Ideas = array();
			$this->IdeasPriority = 3; // $wpdb->get_var('SELECT COUNT(Id)+1 FROM OCTM_Ideas')
			$this->IdeasAssets = $this->ArrayReduce( $wpdb->get_results('SELECT Value AS Id, Name FROM OCTM_Meta WHERE Type = "IdeaAsset" ORDER BY Value', ARRAY_A) );
			$this->IdeasProposers = $this->ArrayReduce( $wpdb->get_results('SELECT Value AS Id, Name FROM OCTM_Meta WHERE Type = "IdeaProposer" ORDER BY Value', ARRAY_A) );
			$this->IdeasStatus = $this->ArrayReduce( $wpdb->get_results('SELECT Value AS Id, Name FROM OCTM_Meta WHERE Type = "IdeaStatus" ORDER BY Value', ARRAY_A) );

			if ($IdeasDB)
			{
				foreach ($IdeasDB as $Idea)
				{
					$this->Ideas[$Idea->Id] = $Idea;
				}
			}

			$this->Members = get_users( array( 'meta_key' => 'OCTMMember', 'meta_value' => 1, 'meta_compare' => '=' ) );
			$this->MemberIds = array(); 
			$this->MemberWeeklyCost = $this->MemberWeeklyCapacity = 0;

			if ($this->Members)
			{
				foreach ($this->Members as $MemberData)
				{
					$this->MemberWeeklyCost += ($MemberData->get('OCTMCapacity') * $MemberData->get('OCTMRate'));
					$this->MemberWeeklyCapacity += $MemberData->get('OCTMCapacity');

					$this->MemberIds[] = $MemberData->ID;
				}
			}
		}


		function AdminPrintScripts()
		{
			wp_enqueue_script('jquery');
		}


		function AdminMenu()
		{
			$AdminDashboardHook = add_menu_page($this->PluginName, 'Contenido', 'read', 'Contenido', array($this, 'AdminDashboard'), $this->PluginURL.'includes/icon.png');

			add_action("admin_print_scripts-$AdminDashboardHook", array($this, 'AdminPrintScripts'));
		}

		function AdminDashboard()
		{
			global $wpdb;

			$NavTab = '#dashboard';

			print '<div class="wrap">';

			if (isset($_POST['Action']))
			{
				if ($_POST['Action'] == 'TaskAdd')
				{
					$NavTab = '#tasks';

					$wpdb->insert( 'OCTM_Tasks', array( 'Idea' => $_POST['Idea'], 'Priority' => $_POST['Priority'], 'Task' => $_POST['Task'], 'Type' => $_POST['Type'], 'Member' => $_POST['Member'], 'Estimate' => $_POST['Estimate'], 'Due' => mysql2date('Y-m-d', $_POST['Due'], false), 'Status' => $_POST['Status'], 'Updated' => current_time('Y-m-d H:i:s'), 'Created' => current_time('Y-m-d H:i:s') ) );

					if ($wpdb->insert_id)
					{
						$this->ShowAlert(__('New task added.', $this->TextDomain));
					}
					else
					{
						$this->ShowAlert(__('New task addition failed.', $this->TextDomain), 'danger');
					}
				}

				if ($_POST['Action'] == 'TaskEdit')
				{
					$NavTab = '#tasks';

					if ( $wpdb->update( 'OCTM_Tasks', array( 'Idea' => $_POST['Idea'], 'Priority' => $_POST['Priority'], 'Task' => $_POST['Task'], 'Type' => $_POST['Type'], 'Member' => $_POST['Member'], 'Estimate' => $_POST['Estimate'], 'Due' => mysql2date('Y-m-d', $_POST['Due'], false), 'Status' => $_POST['Status'] ), array('Id' => $_POST['Id']), null, array('%d') ) )
					{
						$this->ShowAlert(__('Task updated.', $this->TextDomain));
					}
				}

				if ($_POST['Action'] == 'IdeaAdd')
				{
					$NavTab = '#ideas';

					$wpdb->insert( 'OCTM_Ideas', array( 'Idea' => $_POST['Idea'], 'Asset' => $_POST['Asset'], 'Priority' => $_POST['Priority'], 'Due' => mysql2date('Y-m-d', $_POST['Due'], false), 'Proposer' => $_POST['Proposer'], 'Status' => $_POST['Status'], 'Updated' => current_time('Y-m-d H:i:s'), 'Created' => current_time('Y-m-d H:i:s') ) );

					if ($wpdb->insert_id)
					{
						$this->ShowAlert(__('New idea added.', $this->TextDomain));
					}
					else
					{
						$this->ShowAlert(__('New idea addition failed.', $this->TextDomain), 'danger');
					}
				}

				if ($_POST['Action'] == 'IdeaEdit')
				{
					$NavTab = '#ideas';

					if ( $wpdb->update( 'OCTM_Ideas', array( 'Idea' => $_POST['Idea'], 'Asset' => $_POST['Asset'], 'Priority' => $_POST['Priority'], 'Due' => mysql2date('Y-m-d', $_POST['Due'], false), 'Proposer' => $_POST['Proposer'], 'Status' => $_POST['Status'] ), array('Id' => $_POST['Id']), null, array('%d') ) )
					{
						$this->ShowAlert(__('Idea updated.', $this->TextDomain));
					}
				}

				if ($_POST['Action'] == 'MemberAdd')
				{
					$NavTab = '#manageteam';

					if ( update_user_meta( $_POST['Member'], 'OCTMMember', 1 ) && update_user_meta( $_POST['Member'], 'OCTMCapacity', $_POST['Capacity'] ) && update_user_meta( $_POST['Member'], 'OCTMRate', $_POST['Rate'] ) )
					{
						$this->ShowAlert(__('New member added.', $this->TextDomain));
					}
					else
					{
						$this->ShowAlert(__('New member addition failed.', $this->TextDomain), 'danger');
					}
				}

				if ($_POST['Action'] == 'MemberEdit')
				{
					$NavTab = '#manageteam';

					update_user_meta( $_POST['Member'], 'OCTMCapacity', $_POST['Capacity'] );
					update_user_meta( $_POST['Member'], 'OCTMRate', $_POST['Rate'] );

					$this->ShowAlert(__('Member updated.', $this->TextDomain));
				}
			}

			$this->LoadDBData();

			if ($_POST['DateFilter'])
			{
				list($QueryDateMonth, $QueryDateYear) = explode('-', $_POST['DateFilter']);
				$QueryDateFilter = "AND YEAR(Updated) = $QueryDateYear AND MONTH(Updated) = $QueryDateMonth";
				$QueryDateFilterTasks = "AND YEAR(Tasks.Updated) = $QueryDateYear AND MONTH(Tasks.Updated) = $QueryDateMonth";
			}
			else
			{
				$QueryDateFilter = 'AND YEAR(Updated) = YEAR(CURDATE()) AND MONTH(Updated) = MONTH(CURDATE())';
				$QueryDateFilterTasks = 'AND YEAR(Tasks.Updated) = YEAR(CURDATE()) AND MONTH(Tasks.Updated) = MONTH(CURDATE())';
			}

			$TasksDateFilter = $this->ArrayReduce( $wpdb->get_results('SELECT DISTINCT CONCAT_WS("-", MONTH(Updated), YEAR(Updated)) AS Id, CONCAT_WS(" ", MONTHNAME(Updated), YEAR(Updated)) AS Name FROM OCTM_Tasks AS Tasks ORDER BY Updated DESC', ARRAY_A) );
			$TasksSpentToDate = $wpdb->get_var("SELECT SUM(Tasks.Estimate * $wpdb->usermeta.meta_value) FROM OCTM_Tasks AS Tasks, $wpdb->usermeta WHERE Tasks.Status IN (2, 3) AND $wpdb->usermeta.user_id = Tasks.Member AND $wpdb->usermeta.meta_key = 'OCTMRate' $QueryDateFilter");
			$TasksHoursWorked = $wpdb->get_var("SELECT SUM(Tasks.Estimate) FROM OCTM_Tasks AS Tasks WHERE Tasks.Status = 3 $QueryDateFilter");
			$IdeasSubmitted = $wpdb->get_var("SELECT COUNT(Id) FROM OCTM_Ideas WHERE 1 = 1 $QueryDateFilter");
			$TasksAssetsCreated = $wpdb->get_var("SELECT COUNT(*) FROM OCTM_Tasks AS Tasks, OCTM_Ideas AS Ideas WHERE Ideas.Id = Tasks.Idea AND Ideas.Status = 2 AND Tasks.Status = 3 $QueryDateFilterTasks");
			$ChartAssetTypesCreatedData = $wpdb->get_results("SELECT ROUND(SUM(100) / Total) as value, IdeasAssetsColors.Value AS color, Meta.Name AS label FROM (OCTM_Ideas AS Ideas, OCTM_Tasks AS Tasks, OCTM_Meta AS Meta)  CROSS JOIN (SELECT COUNT(*) as Total FROM OCTM_Ideas, OCTM_Tasks WHERE OCTM_Ideas.Status = 3 AND OCTM_Tasks.Idea = OCTM_Ideas.Id AND OCTM_Tasks.Status = 3) AS IdeasTotal  JOIN OCTM_Meta AS IdeasAssetsColors ON IdeasAssetsColors.Name = Ideas.Asset AND IdeasAssetsColors.Type = 'IdeaAssetColor'  WHERE Meta.Type = 'IdeaAsset' AND Meta.Value = Ideas.Asset AND Ideas.Status = 3 AND Tasks.Idea = Ideas.Id AND Tasks.Status = 3 $QueryDateFilterTasks GROUP BY Ideas.Asset");
			$ChartEditorialErrorFixesData = $wpdb->get_results("SELECT ROUND(SUM(100) / Total) as value, TasksStatusColors.Value AS color, Meta.Name AS label FROM (OCTM_Tasks AS Tasks, OCTM_Meta AS Meta)  CROSS JOIN (SELECT COUNT(*) as Total FROM OCTM_Tasks) AS TasksTotal  JOIN OCTM_Meta AS TasksStatusColors ON TasksStatusColors.Name = Tasks.Status AND TasksStatusColors.Type = 'TaskStatusColor'  WHERE Meta.Type = 'TaskStatus' AND Meta.Value = Tasks.Status AND Tasks.Type = 4 $QueryDateFilter GROUP BY Tasks.Status");

		?>


				<link href="<?php print $this->PluginURL; ?>includes/bs3/bootstrap.min.css" rel="stylesheet">
				<link href="<?php print $this->PluginURL; ?>includes/bs3/reset.css" rel="stylesheet">
				<link href="<?php print $this->PluginURL; ?>includes/font-awesome/css/font-awesome.min.css" rel="stylesheet" />
				<link href="<?php print $this->PluginURL; ?>includes/style.css" rel="stylesheet">


				<section id="main-content">
				<section class="wrapper">
				<div class="row">
					<div class="col-lg-6">
						<div class="row">
							<div class="col-md-12">
								<h2><img src="http://conteni.do/wp-content/uploads/2015/01/logocursive_031.png" alt="Contenido Team Manager"></h2>
							</div>
						</div>
					</div>
					<div class="col-md-12">
						<!--tab nav start-->
						<section class="panel">
						<header class="panel-heading tab-bg-dark-navy-blue ">
						<ul class="nav nav-tabs">
							<li>
							<a data-toggle="tab" href="#dashboard">Dashboard</a>
							</li>
							<li>
							<a data-toggle="tab" href="#ideas">Ideas</a>
							</li>
							<li>
							<a data-toggle="tab" href="#tasks">Team Tasks</a>
							</li>
							<li>
							<a data-toggle="tab" href="#mytasks">My Tasks</a>
							</li>
							<?php if (current_user_can('administrator')) : ?>
								<li>
								<a data-toggle="tab" href="#manageteam">Manage Team</a>
								</li>
							<?php endif; ?>
							<li style="float: right; margin: 14px 15px 0 0;">
								<form class="form-inline" role="form" method="post" action="">
									<div class="form-group">
										<select class="form-control m-bot15" name="DateFilter" onchange="this.form.submit();">
											<?php $this->GenerateSelect( $TasksDateFilter, $_POST['DateFilter'] ); ?>
										</select>
									</div>
								</form>
							</li>
						</ul>
						</header>
						<div class="panel-body">
							<div class="tab-content">
								<div id="dashboard" class="tab-pane active">
									<div class="row">
										<div class="col-lg-6">
											<section class="panel">
											<header class="panel-heading">
											Asset Types Created </header>
											<div class="panel-body">
												<div class="chartJS" style="min-height: 250px;">
													<canvas id="ChartAssetTypesCreated" height="250" width="450"></canvas>
												</div>
											</div>
											</section>
										</div>
										<div class="col-sm-6">
											<section class="panel">
											<header class="panel-heading">
											Editorial Error Fixes </header>
											<div class="panel-body">
												<div class="chartJS" style="min-height: 250px;">
													<canvas id="ChartEditorialErrorFixes" height="250" width="450"></canvas>
												</div>
											</div>
											</section>
										</div>
									</div>
									<!--mini statistics start-->
									<div class="row">
										<div class="col-md-3">
											<div class="mini-stat clearfix" style="background:#fafafa">
												<span class="mini-stat-icon green"><i class="fa fa-dollar"></i></span>
												<div class="mini-stat-info">
													<span>$<?php print number_format($TasksSpentToDate); ?></span>
													Spent to Date
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="mini-stat clearfix" style="background:#fafafa">
												<span class="mini-stat-icon orange"><i class="fa fa-clock-o"></i></span>
												<div class="mini-stat-info">
													<span><?php print $TasksHoursWorked ? $TasksHoursWorked : 0; ?></span>
													Hours Worked
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="mini-stat clearfix" style="background:#fafafa">
												<span class="mini-stat-icon pink"><i class="fa fa-lightbulb-o"></i></span>
												<div class="mini-stat-info">
													<span><?php print $IdeasSubmitted; ?></span>
													Ideas Submitted
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<div class="mini-stat clearfix" style="background:#fafafa">
												<span class="mini-stat-icon tar"><i class="fa fa-check-square-o"></i></span>
												<div class="mini-stat-info">
													<span><?php print $TasksAssetsCreated; ?></span>
													Assets Created
												</div>
											</div>
										</div>
									</div>
									<!--mini statistics end-->
									<section class="panel">
									<header class="panel-heading">
									Team Capacity </header>
									<div class="panel-body">
										<table class="table table-hover general-table">
										<thead>
										<tr>
											<th>
												 Team Member
											</th>
											<th>
												 Role
											</th>
											<th>
												 Monthly Capacity
											</th>
										</tr>
										</thead>
										<tbody>
										<?php if ($this->Members) : foreach ($this->Members as $MemberData) : $MemberEstimate = $wpdb->get_var("SELECT ROUND(SUM(Estimate)) FROM OCTM_Tasks AS Tasks WHERE Member = $MemberData->ID $QueryDateFilter"); ?>
											<tr>
												<td>
													<?php print $MemberData->display_name; ?>
												</td>
												<td>
													<?php print ucwords($MemberData->roles[0]); ?>
												</td>
												<td>
													<div class="progress progress-striped progress-xs">
														<div style="width: <?php print round( (100 * $MemberEstimate) / ($MemberData->get('OCTMCapacity') * 4) ); ?>%" aria-valuemin="0" aria-valuemax="<?php print ($MemberData->get('OCTMCapacity') * 4); ?>" aria-valuenow="<?php print $MemberEstimate; ?>" role="progressbar" class="progress-bar progress-bar-success"></div>
													</div>
												</td>
											</tr>
										<?php endforeach; else : ?>
											<tr><td colspan="5"><?php _e('No members found.', $this->TextDomain); ?></td></tr>
										<?php endif; ?>
										</tbody>
										</table>
									</div>
									</section>
									<!-- end of team table-->
								</div>
								<!--end of dash tab-->
								<div id="tasks" class="tab-pane">
									<section class="panel">
									<header class="panel-heading">
									Team Tasks </header>
									<div class="panel-body">
										<a href="#myModal-1a" data-toggle="modal" class="btn btn-success">
										<i class="fa fa-plus-square-o"></i> Add Task </a>
										<table class="table table-hover general-table">
										<thead>
										<tr>
											<th>
												 From Idea
											</th>
											<th>
												 Priority
											</th>
											<th>
												 Task
											</th>
											<th>
												 Task Type
											</th>
											<th>
												 Status
											</th>
											<th>
												 Owner
											</th>
											<th>
												 Time Estimate
											</th>
											<th>
												 Cost
											</th>
											<th>
												 Due Date
											</th>
											<th>
											</th>
										</tr>
										</thead>
										<tbody>
										<?php if ($this->Tasks) : foreach ($this->Tasks as $Task) : $MemberData = get_userdata($Task->Member); ?>
											<tr>
												<td>
													<?php $this->DBDecode($this->Ideas[$Task->Idea]->Idea); ?>
												</td>
												<td>
													<?php print $Task->Priority; ?>
												</td>
												<td>
													<?php $this->DBDecode($Task->Task); ?>
												</td>
												<td>
													<?php print $this->TasksTypes[$Task->Type]; ?>
												</td>
												<td>
													<span class="label <?php print $this->TasksStatusClass[$Task->Status]; ?> label-mini"><?php print $this->TasksStatus[$Task->Status]; ?></span>
												</td>
												<td>
													<?php print $MemberData->display_name; ?>
												</td>
												<td>
													<?php print $Task->Estimate; ?>
												</td>
												<td>
													$<?php print ($MemberData->OCTMRate * $Task->Estimate); ?>
												</td>
												<td>
													<?php print mysql2date('m/d/Y', $Task->Due); ?>
												</td>
												<td>
													<a data-toggle="modal" href="#myModal-1" data-id="<?php print $Task->Id; ?>" data-action="OCTMTaskEdit">Edit</a>
												</td>
											</tr>
										<?php endforeach; else : ?>
											<tr><td colspan="9"><?php _e('No tasks found.', $this->TextDomain); ?></td></tr>
										<?php endif; ?>
										</tbody>
										</table>
										<a href="#myModal-1a" data-toggle="modal" class="btn btn-success">
										<i class="fa fa-plus-square-o"></i> Add Task </a>
									</div>
									</section>
									<!-- Modal Add-->
									<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1a" class="modal fade">
										<div class="modal-dialog" id= "contenido-modal">
											<div class="modal-content">
												<div class="modal-header">
													<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
													<h4 class="modal-title">Add Task</h4>
												</div>
												<div class="modal-body">
													<form class="form-horizontal" role="form" method="post" action="">
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">From Idea</label>
															<div class="col-lg-6">
																<select class="form-control m-bot15" name="Idea" required="required">
																	<?php if ($this->Ideas) : printf ('<option value="">%s</option>', __('- Select -', $this->TextDomain)); foreach ($this->Ideas as $Idea) : ?>
																		<option value="<?php print $Idea->Id; ?>"><?php $this->DBDecode($Idea->Idea); ?></option>
																	<?php endforeach; endif; ?>
																</select>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Priority</label>
															<div class="col-lg-3">
																<select class="form-control m-bot15" name="Priority" required="required">
																	<?php $this->GenerateSelect( range(1, $this->TasksPriority) ); ?>
																</select>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 col-sm-2 control-label">Task</label>
															<div class="col-lg-10">
																<input type="text" class="form-control" id="taskName" name="Task" required="required">
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Task Type</label>
															<div class="col-lg-4">
																<select class="form-control m-bot15" name="Type" required="required">
																	<?php $this->GenerateSelect($this->TasksTypes); ?>
																</select>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Status</label>
															<div class="col-lg-4">
																<select class="form-control m-bot15" name="Status" required="required">
																	<?php $this->GenerateSelect($this->TasksStatus); ?>
																</select>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Owner</label>
															<div class="col-lg-4">
																<?php wp_dropdown_users( array( 'name' => 'Member', 'class' => 'form-control m-bot15', 'include' => implode(',', $this->MemberIds), 'show_option_none' => __('- Select -', $this->TextDomain) ) ); ?>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 col-sm-2 control-label">Time Estimate</label>
															<div class="col-lg-2">
																<input type="text" class="form-control" id="timeEst" name="Estimate" required="required">
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Due Date</label>
															<div class="col-md-6 col-xs-11">
																<input class="form-control form-control-inline input-medium default-date-picker" size="16" type="text" name="Due" required="required">
																<span class="help-block help-block-date">Select date</span>
															</div>
														</div>
														<div class="form-group">
															<div class="panel-body" align="right">
																<input type="hidden" name="Action" value="TaskAdd" />
																<button type="submit" class="btn btn-primary">Save new task</button>
																<button aria-hidden="true" data-dismiss="modal" type="button" class="btn btn-danger">Cancel</button>
															</div>
														</div>
													</form>
												</div>
											</div>
										</div>
									</div>
									<!-- modal -->
									<!-- Modal Edit-->
									<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1" class="modal fade">
										<div class="modal-dialog" id= "contenido-modal">
											<div class="modal-content">
												<div class="modal-header">
													<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
													<h4 class="modal-title">Edit Task</h4>
												</div>
												<div class="modal-body">
													<!-- AJAX Content -->
												</div>
											</div>
										</div>
									</div>
									<!-- modal -->
								</div>
								<!--end of backlog tab-->
								<div id="ideas" class="tab-pane">
									<div class="panel-body">
										<header class="panel-heading">
										Ideas Backlog </header><br>
										<a href="#myModal-1b" data-toggle="modal" class="btn btn-success">
										<i class="fa fa-plus-square-o"></i> New Idea </a>
										<table class="table table-hover general-table">
										<thead>
										<tr>
											<th>
												 Idea
											</th>
											<th>
												 Asset Type
											</th>
											<th>
												 Priority
											</th>
											<th>
												 Due Date
											</th>
											<th>
												 Status
											</th>
											<th>
												 Proposed by
											</th>
											<th>
											</th>
										</tr>
										</thead>
										<tbody>
										<?php if ($this->Ideas) : foreach ($this->Ideas as $Idea) : ?>
											<tr>
												<td>
													<?php $this->DBDecode($Idea->Idea); ?>
												</td>
												<td>
													<?php print $this->IdeasAssets[$Idea->Asset]; ?>
												</td>
												<td>
													<?php print $Idea->Priority; ?>
												</td>
												<td>
													<?php print mysql2date('m/d/Y', $Idea->Due); ?>
												</td>
												<td>
													<span class="label <?php print $this->IdeasStatusClass[$Idea->Status]; ?> label-mini"><?php print $this->IdeasStatus[$Idea->Status]; ?></span>
												</td>
												<td>
													<?php print $this->IdeasProposers[$Idea->Proposer]; ?>
												</td>
												<td>
													<?php if ($Idea->Status == 4) : ?>
														<a data-toggle="modal" href="#myModal-1f" data-id="<?php print $Idea->Id; ?>" data-action="OCTMIdeaPropose">Propose Again</a>
													<?php else : ?>
														<a data-toggle="modal" href="#myModal-1c" data-id="<?php print $Idea->Id; ?>" data-action="OCTMIdeaEdit">Edit</a>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; else : ?>
											<tr><td colspan="5"><?php _e('No ideas found.', $this->TextDomain); ?></td></tr>
										<?php endif; ?>
										</tbody>
										</table>
										<a href="#myModal-1b" data-toggle="modal" class="btn btn-success">
										<i class="fa fa-plus-square-o"></i> New Idea </a>
									</div>
									<!-- Modal Add-->
									<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1b" class="modal fade">
										<div class="modal-dialog" id= "contenido-modal">
											<div class="modal-content">
												<div class="modal-header">
													<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
													<h4 class="modal-title">New Idea</h4>
												</div>
												<div class="modal-body">
													<form class="form-horizontal" role="form" method="post" action="">
														<div class="form-group">
															<label class="col-lg-2 col-sm-2 control-label">Idea</label>
															<div class="col-lg-10">
																<input type="text" class="form-control" name="Idea" required="required">
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Asset Type</label>
															<div class="col-lg-3">
																<select class="form-control m-bot15" name="Asset" required="required">
																	<?php $this->GenerateSelect($this->IdeasAssets); ?>
																</select>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Priority</label>
															<div class="col-lg-3">
																<select class="form-control m-bot15" name="Priority" required="required">
																	<?php $this->GenerateSelect( range(1, $this->IdeasPriority) ); ?>
																</select>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Due Date</label>
															<div class="col-md-6 col-xs-11">
																<input class="form-control form-control-inline input-medium default-date-picker" size="16" type="text" name="Due" required="required">
																<span class="help-block">Select date</span>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Status</label>
															<div class="col-lg-4">
																<select class="form-control m-bot15" name="Status" required="required">
																	<option>Select One</option>
																	<option value="1">Proposed</option>
																	<option value="2">Accepted</option>
																</select>
															</div>
														</div>
														<div class="form-group">
															<label class="col-lg-2 control-label col-sm-2">Proposed by</label>
															<div class="col-lg-4">
																<select class="form-control m-bot15" name="Proposer" required="required">
																	<?php $this->GenerateSelect($this->IdeasProposers); ?>
																</select>
															</div>
														</div>
														<div class="form-group">
															<div class="panel-body" align="right">
																<input type="hidden" name="Action" value="IdeaAdd" />
																<button type="submit" class="btn btn-primary">Save new idea</button>
																<button aria-hidden="true" data-dismiss="modal" type="button" class="btn btn-danger">Cancel</button>
															</div>
														</div>
													</form>
												</div>
											</div>
										</div>
									</div>
									<!-- modal -->
									<!-- Modal Edit-->
									<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1c" class="modal fade">
										<div class="modal-dialog" id= "contenido-modal">
											<div class="modal-content">
												<div class="modal-header">
													<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
													<h4 class="modal-title">Edit Idea</h4>
												</div>
												<div class="modal-body">
													<!-- AJAX Content -->
												</div>
											</div>
										</div>
									</div>
									<!-- modal -->
									<!-- Modal Propose-->
									<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1f" class="modal fade">
										<div class="modal-dialog" id= "contenido-modal">
											<div class="modal-content">
												<div class="modal-header">
													<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
													<h4 class="modal-title">Propose Idea</h4>
												</div>
												<div class="modal-body">
													<!-- AJAX Content -->
												</div>
											</div>
										</div>
									</div>
									<!-- modal -->
								</div>
								<!--end of ideas tab-->
								<?php if (current_user_can('administrator')) : ?>
									<div id="manageteam" class="tab-pane">
										<div class="panel-body">
											<header class="panel-heading">
											Team Members </header><br>
											<a href="#myModal-1e" data-toggle="modal" class="btn btn-success">
											<i class="fa fa-plus-square-o"></i> Add team member </a>
											<table class="table table-hover general-table">
											<thead>
											<tr>
												<th>
													 Name
												</th>
												<th>
													 Role
												</th>
												<th>
													 Weekly Capacity
												</th>
												<th>
													 Hourly Rate
												</th>
												<th>
												</th>
											</tr>
											</thead>
											<tbody>
											<?php if ($this->Members) : foreach ($this->Members as $MemberData) : ?>
												<tr>
													<td>
														<?php print $MemberData->display_name; ?>
													</td>
													<td>
														<?php print ucwords($MemberData->roles[0]); ?>
													</td>
													<td>
														<?php print $MemberData->get('OCTMCapacity'); ?>
													</td>
													<td>
														$<?php print $MemberData->get('OCTMRate'); ?>
													</td>
													<td>
														<a data-toggle="modal" href="#myModal-1d" data-id="<?php print $MemberData->ID; ?>" data-action="OCTMMemberEdit">Edit</a>
													</td>
												</tr>
											<?php endforeach; else : ?>
												<tr><td colspan="5"><?php _e('No members found.', $this->TextDomain); ?></td></tr>
											<?php endif; ?>
											</tbody>
											</table>
											<a href="#myModal-1e" data-toggle="modal" class="btn btn-success">
											<i class="fa fa-plus-square-o"></i> Add team member </a><br>
											<br>
											<!--mini statistics start-->
											<div class="row">
												<div class="col-md-3">
													<div class="mini-stat clearfix" style="background:#fafafa">
														<span class="mini-stat-icon green"><i class="fa fa-dollar"></i></span>
														<div class="mini-stat-info">
															<span>$<?php print number_format($this->MemberWeeklyCost); ?></span>
															Team Weekly Operating Cost
														</div>
													</div>
												</div>
												<div class="col-md-3">
													<div class="mini-stat clearfix" style="background:#fafafa">
														<span class="mini-stat-icon orange"><i class="fa fa-clock-o"></i></span>
														<div class="mini-stat-info">
															<span><?php print $this->MemberWeeklyCapacity; ?></span>
															Team Weekly Capacity (hours)
														</div>
													</div>
												</div>
											</div>
											<!--mini statistics end-->
										</div>
										<!-- Modal Add-->
										<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1e" class="modal fade">
											<div class="modal-dialog" id= "contenido-modal">
												<div class="modal-content">
													<div class="modal-header">
														<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
														<h4 class="modal-title">Add team member</h4>
													</div>
													<div class="modal-body">
														<form class="form-horizontal" role="form" method="post" action="">
															<div class="form-group">
																<label class="col-lg-2 col-sm-2 control-label">Name</label>
																<div class="col-lg-4">
																	<!-- <input type="text" class="form-control"> -->
																	<?php wp_dropdown_users( array( 'name' => 'Member', 'class' => 'form-control m-bot15', 'exclude' => implode(',', $this->MemberIds), 'show_option_none' => __('- Select -', $this->TextDomain) ) ); ?>
																</div>
															</div>
					<!--
															<div class="form-group">
																<label class="col-lg-2 control-label col-sm-2">Role</label>
																<div class="col-lg-4">
																	<select class="form-control m-bot15">
																		<option>Select One</option>
																		<option>Administrator</option>
																		<option>Editor</option>
																		<option>Writer</option>
																		<option>Designer</option>
																		<option>Other</option>
																	</select>
																</div>
															</div>
					-->
															<div class="form-group">
																<label class="col-lg-2 col-sm-2 control-label">Capacity</label>
																<div class="col-lg-2">
																	<input type="text" class="form-control" name="Capacity" required="required">
																</div>
															</div>
															<div class="form-group">
																<label class="col-lg-2 col-sm-2 control-label">Hourly Rate</label>
																<div class="col-lg-3">
																	<div class="input-group">
																		<span class="input-group-addon">$</span>
																		<input type="text" class="form-control" name="Rate" required="required">
																	</div>
																</div>
															</div>
															<div class="form-group">
																<div class="panel-body" align="right">
																	<input type="hidden" name="Action" value="MemberAdd" />
																	<button type="submit" class="btn btn-primary">Save new team member</button>
																	<button aria-hidden="true" data-dismiss="modal" type="button" class="btn btn-danger">Cancel</button>
																</div>
															</div>
														</form>
													</div>
												</div>
											</div>
										</div>
										<!-- modal -->
										<!-- Modal Edit-->
										<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-1d" class="modal fade">
											<div class="modal-dialog" id= "contenido-modal">
												<div class="modal-content">
													<div class="modal-header">
														<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
														<h4 class="modal-title">Edit team member</h4>
													</div>
													<div class="modal-body">
														<!-- AJAX Content -->
													</div>
												</div>
											</div>
										</div>
									</div>
									<!--end of team tab-->
								<?php endif; ?>
								<div id="mytasks" class="tab-pane">
									<section class="panel">
									<header class="panel-heading">
									Tasks assigned to me </header>
									<div class="panel-body">
										<table class="table table-hover general-table">
										<thead>
										<tr>
											<th>
												 From Idea
											</th>
											<th>
												 Priority
											</th>
											<th>
												 Task
											</th>
											<th>
												 Task Type
											</th>
											<th>
												 Status
											</th>
											<th>
												 Owner
											</th>
											<th>
												 Time Estimate
											</th>
											<th>
												 Cost
											</th>
											<th>
												 Due Date
											</th>
											<th>
											</th>
										</tr>
										</thead>
										<tbody>
										<?php if ($this->Tasks && $this->TasksMy) : foreach ($this->Tasks as $Task) : if ($Task->Member == get_current_user_id()) : ?>
											<tr>
												<td>
													<?php $this->DBDecode($this->Ideas[$Task->Idea]->Idea); ?>
												</td>
												<td>
													<?php print $Task->Priority; ?>
												</td>
												<td>
													<?php $this->DBDecode($Task->Task); ?>
												</td>
												<td>
													<?php print $this->TasksTypes[$Task->Type]; ?>
												</td>
												<td>
													<span class="label <?php print $this->TasksStatusClass[$Task->Status]; ?> label-mini"><?php print $this->TasksStatus[$Task->Status]; ?></span>
												</td>
												<td>
													<?php print get_userdata($Task->Member)->display_name; ?>
												</td>
												<td>
													<?php print $Task->Estimate; ?>
												</td>
												<td>
													$<?php print (get_userdata($Task->Member)->OCTMRate * $Task->Estimate); ?>
												</td>
												<td>
													<?php print mysql2date('m/d/Y', $Task->Due); ?>
												</td>
												<td>
													<a data-toggle="modal" href="#myModal-myb" data-id="<?php print $Task->Id; ?>" data-action="OCTMTaskEdit">Edit</a>
												</td>
											</tr>
										<?php endif; endforeach; else : ?>
											<tr><td colspan="9"><?php _e('No tasks found.', $this->TextDomain); ?></td></tr>
										<?php endif; ?>
										</tbody>
										</table>
									</div>
									</section>
									<!-- Modal Edit-->
									<div aria-hidden="true" aria-labelledby="myModalLabel" role="dialog" tabindex="-1" id="myModal-myb" class="modal fade">
										<div class="modal-dialog" id= "contenido-modal">
											<div class="modal-content">
												<div class="modal-header">
													<button aria-hidden="true" data-dismiss="modal" class="close" type="button">×</button>
													<h4 class="modal-title">Edit Task</h4>
												</div>
												<div class="modal-body">
													<!-- AJAX Content -->
												</div>
											</div>
										</div>
									</div>
									<!-- modal -->
								</div>
								<!--end of tasks tab-->
							</div>
						</div>
						</section>
						<!--tab nav start-->
						<!--right sidebar end-->
					</div>
				</div>
				</section>
				</section>

				<div id="ModalLoading" class="hide">
					<div class="progress progress-striped active" style="width: 50%; margin: 0 auto;"><div style="width: 100%" aria-valuemax="100" aria-valuemin="0" aria-valuenow="100" role="progressbar" class="progress-bar progress-bar-info">Loading...</div></div>
				</div>

				<script type="text/javascript">

					var ChartAssetTypesCreatedData = <?php print json_encode($ChartAssetTypesCreatedData, JSON_NUMERIC_CHECK); ?>;
					var ChartEditorialErrorFixesData = <?php print json_encode($ChartEditorialErrorFixesData, JSON_NUMERIC_CHECK); ?>;

				</script>

				<script src="<?php print $this->PluginURL; ?>includes/bs3/bootstrap.min.js"></script>
				<link href="<?php print $this->PluginURL; ?>includes/datepicker/datepicker.css" rel="stylesheet" />
				<script src="<?php print $this->PluginURL; ?>includes/datepicker/datepicker.js"></script>
				<script src="<?php print $this->PluginURL; ?>includes/chart.js"></script>
				<script src="<?php print $this->PluginURL; ?>includes/script.js"></script>

				<script type="text/javascript">

					jQuery(document).ready(function($)
					{
						<?php if ($NavTab) : ?>
							$('.nav-tabs a[href="<?php print $NavTab; ?>"]').click();
						<?php endif; ?>
					});

				</script>


			</div> <!-- wrap -->

		<?php

		}


		function AJAXTaskEdit()
		{
			global $wpdb;

			if (!isset($_POST['Id']))
			{
				$this->ShowAlert(__('Required parameters missing.', $this->TextDomain), 'danger');
			}
			elseif ( $Task = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM OCTM_Tasks WHERE Id = %d', $_POST['Id'] ) ) )
			{
				$this->LoadDBData();
				$IdeaDue = $wpdb->get_var( "SELECT Due FROM OCTM_Ideas WHERE Id = $Task->Idea" );

			?>

				<form class="form-horizontal" role="form" method="post" action="">
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">From Idea</label>
						<div class="col-lg-6">
							<select class="form-control m-bot15" name="Idea" required="required">
								<?php if ($this->Ideas) : printf ('<option value="">%s</option>', __('- Select -', $this->TextDomain)); foreach ($this->Ideas as $Idea) : ?>
									<option value="<?php print $Idea->Id; ?>" <?php $this->CheckSelected($Task->Idea, $Idea->Id); ?>><?php $this->DBDecode($Idea->Idea); ?></option>
								<?php endforeach; endif; ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Priority</label>
						<div class="col-lg-3">
							<select class="form-control m-bot15" name="Priority" required="required">
								<?php $this->GenerateSelect( range(1, $this->TasksPriority), $Task->Priority ); ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 col-sm-2 control-label">Task</label>
						<div class="col-lg-10">
							<input type="text" class="form-control" id="taskName" name="Task" value="<?php $this->DBDecode($Task->Task); ?>" required="required">
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Task Type</label>
						<div class="col-lg-4">
							<select class="form-control m-bot15" name="Type" required="required">
								<?php $this->GenerateSelect($this->TasksTypes, $Task->Type); ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Status</label>
						<div class="col-lg-4">
							<select class="form-control m-bot15" name="Status" required="required">
								<?php $this->GenerateSelect($this->TasksStatus, $Task->Status); ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Owner</label>
						<div class="col-lg-4">
							<?php wp_dropdown_users( array( 'name' => 'Member', 'class' => 'form-control m-bot15', 'include' => implode(',', $this->MemberIds), 'selected' => $Task->Member, 'show_option_none' => __('- Select -', $this->TextDomain) ) ); ?>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 col-sm-2 control-label">Time Estimate</label>
						<div class="col-lg-2">
							<input type="text" class="form-control" id="timeEst" name="Estimate" value="<?php print $Task->Estimate; ?>" required="required">
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Due Date</label>
						<div class="col-md-6 col-xs-11">
							<input class="form-control form-control-inline input-medium default-date-picker" size="16" type="text" name="Due" value="<?php print mysql2date('m/d/Y', $Task->Due); ?>" required="required" data-date-end-date="<?php print mysql2date('m/d/Y', $IdeaDue); ?>">
							<span class="help-block help-block-date">Select date</span>
						</div>
					</div>
					<div class="form-group">
						<div class="panel-body" align="right">
							<input type="hidden" name="Id" value="<?php print $_POST['Id']; ?>" />
							<input type="hidden" name="Action" value="TaskEdit" />
							<button type="submit" class="btn btn-primary">Save changes</button>
							<button aria-hidden="true" data-dismiss="modal" type="button" class="btn btn-danger">Cancel</button>
						</div>
					</div>
				</form>

			<?php
			}
			else
			{
				$this->ShowAlert(__('No task found.', $this->TextDomain), 'danger');
			}

			exit;
		}


		function AJAXTaskEndDate()
		{
			global $wpdb;

			if (!isset($_POST['Id']))
			{
				print json_encode( array( 'Status' => 'Error', 'Message' => 'Required parameters missing.' ) );
			}
			elseif ( $IdeaDue = $wpdb->get_var( $wpdb->prepare( 'SELECT Due FROM OCTM_Ideas WHERE Id = %d', $_POST['Id'] ) ) )
			{
				print json_encode( array( 'Status' => 'OK', 'Data' => mysql2date('m/d/Y', $IdeaDue) ) );
			}
			else
			{
				print json_encode( array( 'Status' => 'Error', 'Message' => 'No idea found.' ) );
			}

			exit;
		}


		function AJAXIdeaEdit()
		{
			global $wpdb;

			if (!isset($_POST['Id']))
			{
				$this->ShowAlert(__('Required parameters missing.', $this->TextDomain), 'danger');
			}
			elseif ( $Idea = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM OCTM_Ideas WHERE Id = %d', $_POST['Id'] ) ) )
			{
				$this->LoadDBData();

			?>

				<form class="form-horizontal" role="form" method="post" action="">
					<div class="form-group">
						<label class="col-lg-2 col-sm-2 control-label">Idea</label>
						<div class="col-lg-10">
							<input type="text" class="form-control" name="Idea" value="<?php $this->DBDecode($Idea->Idea); ?>" required="required">
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Asset Type</label>
						<div class="col-lg-3">
							<select class="form-control m-bot15" name="Asset" required="required">
								<?php $this->GenerateSelect($this->IdeasAssets, $Idea->Asset); ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Priority</label>
						<div class="col-lg-3">
							<select class="form-control m-bot15" name="Priority" required="required">
								<?php $this->GenerateSelect( range(1, $this->IdeasPriority), $Idea->Priority ); ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Due Date</label>
						<div class="col-md-6 col-xs-11">
							<input class="form-control form-control-inline input-medium default-date-picker" size="16" type="text" name="Due" value="<?php print mysql2date('m/d/Y', $Idea->Due); ?>" required="required">
							<span class="help-block">Select date</span>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Status</label>
						<div class="col-lg-4">
							<select class="form-control m-bot15" name="Status" required="required">
								<?php $this->GenerateSelect($this->IdeasStatus, $Idea->Status); ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 control-label col-sm-2">Proposed by</label>
						<div class="col-lg-4">
							<select class="form-control m-bot15" name="Proposer" required="required">
								<?php $this->GenerateSelect($this->IdeasProposers, $Idea->Proposer); ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="panel-body" align="right">
							<input type="hidden" name="Id" value="<?php print $_POST['Id']; ?>" />
							<input type="hidden" name="Action" value="IdeaEdit" />
							<button type="submit" class="btn btn-primary">Save changes</button>
							<button aria-hidden="true" data-dismiss="modal" type="button" class="btn btn-danger">Cancel</button>
						</div>
					</div>
				</form>

			<?php
			}
			else
			{
				$this->ShowAlert(__('No idea found.', $this->TextDomain), 'danger');
			}

			exit;
		}


		function AJAXIdeaPropose()
		{
			global $wpdb;

			if (!isset($_POST['Id']))
			{
				$this->ShowAlert(__('Required parameters missing.', $this->TextDomain), 'danger');
			}
			elseif ( $Idea = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM OCTM_Ideas WHERE Id = %d', $_POST['Id'] ) ) )
			{
				if ( $wpdb->update( 'OCTM_Ideas', array( 'Status' => 1 ), array('Id' => $_POST['Id']), null, array('%d') ) )
				{
					$this->ShowAlert(__('Idea updated.', $this->TextDomain));
				}
				else
				{
					$this->ShowAlert(__('Idea updation failed.', $this->TextDomain), 'danger');
				}
			}
			else
			{
				$this->ShowAlert(__('No idea found.', $this->TextDomain), 'danger');
			}

			exit;
		}


		function AJAXMemberEdit()
		{
			global $wpdb;

			if (!isset($_POST['Id']))
			{
				$this->ShowAlert(__('Required parameters missing.', $this->TextDomain), 'danger');
			}
			elseif ( $MemberData = get_userdata($_POST['Id']) )
			{
			?>

				<form class="form-horizontal" role="form" method="post" action="">
					<div class="form-group">
						<label class="col-lg-2 col-sm-2 control-label">Name</label>
						<div class="col-lg-5">
							<input type="text" class="form-control" value="<?php print $MemberData->display_name; ?>" disabled="disabled">
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 col-sm-2 control-label">Capacity</label>
						<div class="col-lg-2">
							<input type="text" class="form-control" name="Capacity" value="<?php print $MemberData->get('OCTMCapacity'); ?>" required="required">
						</div>
					</div>
					<div class="form-group">
						<label class="col-lg-2 col-sm-2 control-label">Hourly Rate</label>
						<div class="col-lg-3">
							<div class="input-group">
								<span class="input-group-addon">$</span>
								<input type="text" class="form-control" name="Rate" value="<?php print $MemberData->get('OCTMRate'); ?>" required="required">
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="panel-body" align="right">
							<input type="hidden" name="Member" value="<?php print $_POST['Id']; ?>" />
							<input type="hidden" name="Action" value="MemberEdit" />
							<button type="submit" class="btn btn-primary">Save changes</button>
							<button aria-hidden="true" data-dismiss="modal" type="button" class="btn btn-danger">Cancel</button>
						</div>
					</div>
				</form>

			<?php
			}
			else
			{
				$this->ShowAlert(__('No member found.', $this->TextDomain), 'danger');
			}

			exit;
		}


		function ShowAlert($Message = '', $Type = 'success')
		{
			printf('<div class="alert alert-%s alert-dismissible" role="alert"> <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>%s</div>', $Type, $Message);
		}


		function ArrayReduce($Array)
		{
			$Data = array();

			foreach ($Array as $Item)
			{
				if (isset($Item['Id'], $Item['Name']))
				{
					$Data[$Item['Id']] = $Item['Name'];
				}
			}

			return $Data;
		}


		function DBDecode($Data = '', $Display = true)
		{
			if ($Display)
			{
				print stripslashes(html_entity_decode($Data, ENT_QUOTES));
			}
			else
			{
				return stripslashes(html_entity_decode($Data, ENT_QUOTES));
			}
		}


		function GenerateSelect($Data = array(), $CurrentValue = null)
		{
			if (is_array($Data) && count($Data))
			{
				$IsIndexed = array_values($Data) === $Data;
				printf ('<option value="">%s</option>', __('- Select -', $this->TextDomain));

				foreach ($Data as $Key => $Value)
				{
					if ($IsIndexed)
					{
						$Selected = ($CurrentValue && $Value == $CurrentValue) ? 'selected="selected"' : '';

						printf('<option value="%s"%s>%s</option>', $Value, $Selected, $Value);
					}
					else
					{
						$Selected = ($CurrentValue && $Key == $CurrentValue) ? 'selected="selected"' : '';

						printf('<option value="%s"%s>%s</option>', $Key, $Selected, $Value);
					}
				}
			}
		}


		function CheckSelected($SavedValue, $CurrentValue, $Type = 'select', $Display = true)
		{
			if ( (is_array($SavedValue) && in_array($CurrentValue, $SavedValue)) || ($SavedValue == $CurrentValue) )
			{
				switch ($Type)
				{
					case 'select':
						if ($Display)
						{
							print 'selected="selected"';
						}
						else
						{
							return 'selected="selected"';
						}
						break;
					case 'radio':
					case 'checkbox':
						if ($Display)
						{
							print 'checked="checked"';
						}
						else
						{
							return 'checked="checked"';
						}
						break;
				}
			}
		}

	}

	OdysseyCTM::Instance();
}

?>