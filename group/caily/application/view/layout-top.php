<!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->
        <aside id="layout-menu" class="layout-menu menu-vertical menu">
          <div class="layout-menu-collapsed-wrapper" style="position: fixed; top: 0;">
          <div class="app-brand demo">
            <a href="/" class="app-brand-link">
              <span class="app-brand-logo demo">
                <span class="text-primary">
                  <img src="<?=ROOT?>assets/img/<?=APP_LOGO_DARK?>" alt="" width="30"
                  data-app-light-img="<?=APP_LOGO?>"
                  data-app-dark-img="<?=APP_LOGO_DARK?>" />
                </span>
              </span>
              <span class="app-brand-text demo menu-text fw-bold ms-3" data-i18n="<?=APP_NAME?>"><?=APP_NAME?> </span>
            </a>

            <a href="javascript:void(0);" title="メニューを閉じる" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="icon-base fas fa-circle-dot d-none d-xl-block"></i>
              <i class="icon-base fa fa-times d-block d-xl-none"></i>
            </a>
          </div>

          <div class="menu-inner-shadow"></div>
          <ul class="menu-inner py-1">
            <!-- Dashboards -->
            <li class="menu-item <?php if($directory == 'top') echo 'active'; ?>">
              <a href="<?=$root?>" class="menu-link">
                <i class="menu-icon icon-base ti tabler-home"></i>
                <div data-i18n="ホーム">ホーム</div>
              </a>
            </li>

            <!-- Layouts -->
            <?php if($_SESSION['show_project'] == 1){ ?>
            <li class="menu-item <?php if($directory == 'project' || $directory == 'parent_project' || $directory == 'price_list') echo 'active open'; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base fa fa-briefcase"></i>
                <div><span data-i18n="プロジェクト">プロジェクト</span><span class="badge bg-label-primary ms-2"><?=$_SESSION['isProjectManager'] ? 'PM' : ''?></span></div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item <?php if($directory == 'parent_project') echo 'active'; ?>">
                  <a href="<?=$root?>parent_project/" class="menu-link">
                    <div data-i18n="建物一覧">建物一覧</div>
                  </a>
                </li>
                <li class="menu-item <?php if($directory == 'project' && $page != 'project_gantt' && $page != 'custom_fields' && $page != 'task_overview' && $page != 'employee_statistics' && $page != 'team_revenue_targets' && $page != 'revenue_statistics' && $page != 'invoiced_projects') echo 'active'; ?>">
                  <a href="<?=$root?>project/" class="menu-link">
                    <div data-i18n="案件一覧">案件一覧</div>
                  </a>
                </li>
                <li class="menu-item <?php if($directory == 'project' && $page == 'task_overview') echo 'active'; ?>">
                  <a href="<?=$root?>project/task_overview.php" class="menu-link">
                    <div data-i18n="タスク一覧">タスク一覧</div>
                  </a>
                </li>
                <li class="menu-item <?php if($directory == 'project' && $page == 'project_gantt') echo 'active'; ?>">
                  <a href="<?=$root?>project/project_gantt.php" class="menu-link">
                    <div data-i18n="案件ガントチャート">案件ガントチャート</div>
                  </a>
                </li>
                
                <?php if($_SESSION['authority'] == 'administrator'){ ?>
                  <li class="menu-item <?php if($directory == 'project' && $page == 'custom_fields') echo 'active'; ?>">
                    <a href="<?=$root?>project/custom_fields.php" class="menu-link">
                      <div data-i18n="カスタムフィールド">カスタムフィールド</div>
                    </a>
                  </li>
                  <!-- <li class="menu-item <?php if($directory == 'price_list') echo 'active'; ?>">
                    <a href="<?=$root?>price_list" class="menu-link">
                      <div data-i18n="価格表管理">価格表管理</div>
                    </a>
                  </li> -->
                 
                <?php } ?>
                
              </ul>
            </li>
            <?php } ?>

            <?php 
            
            $_revenuePermModel = new ApplicationModel();
            $showRevenueStatsMenu = ($_SESSION['authority'] ?? '') === 'administrator'
                || $_revenuePermModel->hasDepartmentPermission('project_director_stat');

            if($showRevenueStatsMenu){ ?>
              <li class="menu-item <?php if($directory == 'project' || $directory == 'parent_project' || $directory == 'price_list') echo 'active open'; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base fa fa-chart-bar"></i>
                <div><span data-i18n="統計情報">統計情報</span></div>
              </a>
              <ul class="menu-sub">
                
                <?php
               
                if ($showRevenueStatsMenu) {
                ?>
                <li class="menu-item <?php if($directory == 'project' && $page == 'revenue_statistics') echo 'active'; ?>">
                  <a href="<?=$root?>project/revenue_statistics.php" class="menu-link">
                    <div data-i18n="月次売上統計">月次売上統計</div>
                  </a>
                </li>
                <!-- <li class="menu-item <?php if($directory == 'project' && $page == 'invoiced_projects') echo 'active'; ?>">
                  <a href="<?=$root?>project/invoiced_projects.php" class="menu-link">
                    <div data-i18n="入金管理">入金管理</div>
                  </a>
                </li> -->
                <?php } ?>
                
                <?php if($_SESSION['authority'] == 'administrator' && $_SESSION['group'] != '7'  && $_SESSION['group'] != '6'){?>
                  <li class="menu-item <?php if($directory == 'project' && $page == 'employee_statistics') echo 'active'; ?>">
                    <a href="<?=$root?>project/employee_statistics.php" class="menu-link">
                      <div data-i18n="従業員統計">従業員統計</div>
                    </a>
                  </li>
                  <li class="menu-item <?php if($directory == 'project' && $page == 'team_revenue_targets') echo 'active'; ?>">
                    <a href="<?=$root?>project/team_revenue_targets.php" class="menu-link">
                      <div data-i18n="売上目標設定">売上目標設定</div>
                    </a>
                  </li>
                <?php } ?>
                
              </ul>
            </li>
            <?php } ?>

            <?php
            $form_pending_badge = 0;
            $form_unread_comment_badge = 0;
            if (!empty($_SESSION['userid'])) {
              require_once DIR_MODEL.'request.php';
              $reqModel = new Request();
              $reqModel->connect();
              $form_pending_badge = $reqModel->countPendingBadge();
              $form_unread_comment_badge = $reqModel->countUnreadCommentBadge();
              $reqModel->close();
            }
            ?>
             
             <?php if($_SESSION['group'] != '7'){ ?>
           <li class="menu-item <?php if($directory == 'form') echo 'active open'; ?>">
              <a href="<?=$root?>form/index.php" class="menu-link">
                <i class="menu-icon icon-base fa fa-file-alt"></i>
                <div data-i18n="申請・承認">申請・承認</div>
                <?php if ($form_pending_badge > 0) { ?><span class="badge badge_number bg-warning text-dark rounded-pill ms-auto"><?= $form_pending_badge ?></span><?php } ?>
                <span id="form-unread-comment-badge" class="badge badge_number bg-danger rounded-pill ms-1<?= ($form_unread_comment_badge > 0 ? '' : ' d-none') ?>"><?= intval($form_unread_comment_badge) ?></span>
              </a>
            </li>
             <?php } ?>
              <li class="menu-item <?php if($directory == 'schedule') echo 'active open'; ?>">
                <a href="<?=$root?>schedule/" class="menu-link">
                  <i class="menu-icon icon-base ti tabler-calendar-event"></i>
                  <div data-i18n="カレンダー">カレンダー</div>
                </a>
              </li>
            <?php if($_SESSION['group'] != '7' && $_SESSION['group'] != '6'){ ?>
              <li class="menu-item <?php if($directory == 'timecard' && ($page == 'index' || $page == 'group')) echo 'active'; ?>">
                <a href="<?=$root?>timecard/" class="menu-link">
                  <i class="menu-icon icon-base ti tabler-clock"></i>
                  <div data-i18n="タイムカード">タイムカード</div>
                </a>
              </li>
            
            <!-- <li class="menu-item <?php if($directory == 'addressbook') echo 'active open'; ?>">
              <a href="<?=$root?>addressbook/" class="menu-link">
                <i class="menu-icon icon-base ti tabler-address-book"></i>
                <div data-i18n="アドレス帳">アドレス帳</div>
              </a>
            </li> -->
           
            <?php } ?>

            <?php if($_SESSION['show_project'] == 1 && $_SESSION['group'] != '7'){ ?>
            <li class="menu-item <?php if($directory == 'customer') echo 'active open'; ?>">
              <a href="<?=$root?>customer/" class="menu-link">
                <i class="menu-icon icon-base fa fa-users"></i>
                <div data-i18n="顧客情報">顧客情報</div>
              </a>
            </li>
            <?php } ?>

            <li class="menu-item <?php if($directory == 'member') echo 'active'; ?>">
              <a href="<?=$root?>member/" class="menu-link">
                <i class="menu-icon icon-base ti tabler-users"></i>
                <div data-i18n="ユーザー一覧">ユーザー一覧</div>
              </a>
            </li>
            <?php if($_SESSION['group'] != '7' && $_SESSION['group'] != '6'){ ?>
            <li class="menu-item <?php if($directory == 'storage') echo 'active open'; ?>">
              <a href="<?=$root?>storage/" class="menu-link">
                <i class="menu-icon icon-base ti tabler-server-2"></i>
                <div data-i18n="ファイル共有">ファイル共有</div>
              </a>
            </li>
            <li class="menu-item <?php if($directory == 'forum') echo 'active open'; ?>">
              <a href="<?=$root?>forum/?folder=0" class="menu-link">
                <i class="menu-icon icon-base ti tabler-news"></i>
                <div data-i18n="お知らせ">お知らせ</div>
              </a>
            </li>
            <?php } ?>
            


            <?php if($_SESSION['authority'] == 'administrator' && $_SESSION['group'] != '7' && $_SESSION['group'] != '6'){
              $active = '';
              if($directory == 'setting') {
                $active = 'active open';
                if($page == 'branch') $active1 = 'active';
                if($page == 'department') $active2 = 'active';
                if($page == 'team') $active6 = 'active';
                if($page == 'seal') $active7 = 'active';
                if($page == 'backup') $active8 = 'active';
              }
              if($directory == 'administration'){
                $active = 'active open';
                $active3 = 'active';
              }
              if($directory == 'timecard' && $page == 'holiday'){
                $active = 'active open';
                $active4 = 'active';
              }
              if($directory == 'timecard' && ($page == 'config' || $page == 'add_config')){
                $active = 'active open';
                $active5 = 'active';
              }

              ?>
            <li class="menu-item <?php echo $active; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-settings"></i>
                <div data-i18n="共通設定">共通設定</div>
              </a>

              <ul class="menu-sub">
                <li class="menu-item <?php echo $active1; ?>">
                  <a href="<?=$root?>setting/branch.php" class="menu-link">
                    <div data-i18n="支社設定">支社設定</div>
                  </a>
                </li>
                <li class="menu-item <?php echo $active2; ?>">
                  <a href="<?=$root?>setting/department.php" class="menu-link">
                    <div data-i18n="部署設定">部署設定</div>
                  </a>
                </li>
                <li class="menu-item <?php echo $active6; ?>">
                  <a href="<?=$root?>setting/team.php" class="menu-link">
                    <div data-i18n="チーム設定">チーム設定</div>
                  </a>
                </li>
                <li class="menu-item <?php echo $active7; ?>">
                  <a href="<?=$root?>setting/seal.php" class="menu-link">
                    <div data-i18n="印鑑設定">印鑑設定</div>
                  </a>
                </li>
               
                <li class="menu-item <?php echo $active3; ?>">
                  <a href="<?=$root?>group/" class="menu-link">
                    <div data-i18n="グループ設定">グループ設定</div>
                  </a>
                </li>
                <li class="menu-item <?php echo $active4; ?>">
                  <a href="<?=$root?>timecard/holiday.php" class="menu-link">
                    <div data-i18n="休日設定">休日設定</div>
                  </a>
                </li>
                <li class="menu-item <?php echo $active5; ?>">
                  <a href="<?=$root?>timecard/config.php" class="menu-link">
                    <div data-i18n="タイムカード設定">タイムカード設定</div>
                  </a>
                </li>
                <?php if (($_SESSION['userid'] ?? '') === 'admin') { ?>
                <li class="menu-item <?php echo $active8; ?>">
                  <a href="<?=$root?>setting/backup.php" class="menu-link">
                    <div data-i18n="DBバックアップ">DBバックアップ</div>
                  </a>
                </li>
                <?php } ?>
              </ul>
            </li>
            <?php } ?>

            
          </ul>
          </div>
        </aside>
        <style>
          
          .layout-menu-hover .app-brand .layout-menu-toggle {
              display: block;
          }
          .layout-menu-collapsed .layout-menu-toggle{
            display: none;
          }
          .layout-menu-collapsed .layout-menu-collapsed-wrapper{
            width: var(--bs-menu-collapsed-width);
          }
          .layout-menu-hover .layout-menu-collapsed-wrapper{
            width: auto;
          }
        </style>

        <div class="menu-mobile-toggler d-xl-none rounded-1">
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
            <i class="ti tabler-menu icon-base"></i>
            <i class="ti tabler-chevron-right icon-base"></i>
          </a>
        </div>
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->

          <nav
            class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
            id="layout-navbar">
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                <i class="icon-base ti tabler-menu-2 icon-md"></i>
              </a>
            </div>

            <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
              <!-- Search -->
              <div class="navbar-nav align-items-center">
                <div class="nav-item navbar-search-wrapper px-md-0 px-2 mb-0">
                  <a class="nav-item nav-link search-toggler d-flex align-items-center px-0 navbar-command-palette-trigger" href="javascript:void(0);" id="navbar-command-palette-trigger" title="コマンドパレット (F1 / Ctrl+K)">
                    <i class="icon-base ti tabler-search icon-md me-2"></i>
                    <span class="d-none d-md-inline-block text-body-secondary fw-normal" data-i18n="コマンドパレットを開く">検索 (F1 / Ctrl+K)</span>
                  </a>
                </div>
              </div>

              <!-- /Search -->

              <ul class="navbar-nav flex-row align-items-center ms-md-auto">
              <?php
              $__requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
              $__showProjectDisplayTimezone = (bool) preg_match('#/(project|parent_project)(/|$)#', $__requestUri);
              ?>
              <?php if ($__showProjectDisplayTimezone): ?>
              <li class="nav-item d-flex align-items-center me-2 me-xl-1">
                <span class="badge bg-label-info text-nowrap small" id="nav-display-timezone-badge" title="">
                  <i class="fa fa-clock me-1"></i><span id="nav-display-timezone-text" data-i18n="表示: JST (日本)">表示: JST (日本)</span>
                </span>
              </li>
              <?php endif; ?>
              <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
                  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class="icon-base fa fa-language icon-22px text-heading"></i>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item js-change-language" href="javascript:void(0);" data-language="en" data-text-direction="ltr">
                        <span>日本語</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item js-change-language" href="javascript:void(0);" data-language="vi" data-text-direction="ltr">
                        <span>Tiếng Việt</span>
                      </a>
                    </li>
                  </ul>
                </li>
                <!--/ Language -->
                <!-- Style Switcher -->
                <li class="nav-item dropdown me-3 me-xl-2">
                  <a
                    class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
                    id="nav-theme"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <i class="icon-base ti tabler-sun icon-22px theme-icon-active text-heading"></i>
                    <span class="d-none ms-2" id="nav-theme-text" data-i18n="テーマ">テーマ</span>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
                    <li>
                      <button
                        type="button"
                        class="dropdown-item align-items-center active"
                        data-bs-theme-value="light"
                        aria-pressed="false">
                        <span><i class="icon-base ti tabler-sun icon-22px me-3" data-icon="sun"></i><span data-i18n="ライト">ライト</span></span>
                      </button>
                    </li>
                    <li>
                      <button
                        type="button"
                        class="dropdown-item align-items-center"
                        data-bs-theme-value="dark"
                        aria-pressed="true">
                        <span
                          ><i class="icon-base ti tabler-moon-stars icon-22px me-3" data-icon="moon-stars"></i
                          ><span data-i18n="ダーク">ダーク</span></span
                        >
                      </button>
                    </li>
                    <li>
                      <button
                        type="button"
                        class="dropdown-item align-items-center"
                        data-bs-theme-value="system"
                        aria-pressed="false">
                        <span
                          ><i
                            class="icon-base ti tabler-device-desktop-analytics icon-22px me-3"
                            data-icon="device-desktop-analytics"></i
                          ><span data-i18n="システム">システム</span></span
                        >
                      </button>
                    </li>
                  </ul>
                </li>
                <!-- / Style Switcher-->

                <!-- Quick links  -->
                <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-3 me-xl-2">
                  <a
                    class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="outside"
                    aria-expanded="false">
                    <i class="icon-base ti tabler-layout-grid-add icon-22px text-heading"></i>
                  </a>
                  <div class="dropdown-menu dropdown-menu-end p-0">
                    <div class="dropdown-menu-header border-bottom">
                      <div class="dropdown-header d-flex align-items-center py-3">
                        <h6 class="mb-0 me-auto" data-i18n="ショートカット">ショートカット</h6>
                        
                      </div>
                    </div>
                    <div class="dropdown-shortcuts-list scrollable-container">
                      <div class="row row-bordered overflow-visible">
                        <div class="dropdown-shortcuts-item col">
                          <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                            <i class="icon-base ti tabler-tool icon-26px text-heading"></i>
                          </span>
                          <a href="http://tools.caily.com.vn/?lang=ja" target="_blank" class="stretched-link" data-i18n="CAILYツール">CAILYツール</a>
                        </div>
                        <div class="dropdown-shortcuts-item col">
                          <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                            <i class="icon-base ti tabler-server icon-26px text-heading"></i>
                          </span>
                          <a href="https://caily.ddns.net/" target="_blank" class="stretched-link" data-i18n="CAILY Cloud">CAILY Cloud</a>
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
                <!-- Quick links -->

                <!-- Change log -->
                <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2">
                  <a
                    class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="outside"
                    aria-expanded="false">
                    <i class="icon-base ti tabler-history icon-22px text-heading"></i>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end p-0">
                    <li class="dropdown-menu-header border-bottom">
                      <div class="dropdown-header d-flex align-items-center py-3">
                        <h6 class="mb-0 me-auto" data-i18n="変更履歴">変更履歴</h6>
                      </div>
                    </li>
                    <li class="dropdown-notifications-list scrollable-container">
                      <ul class="list-group list-group-flush">
                        <li class="list-group-item list-group-item-action dropdown-notifications-item">
                          <div class="d-flex">
                            <div class="flex-grow-1">
                              <h6 class="small mb-1">2025年7月15日</h6>
                              <small class="mb-1 d-block text-body">
                                <ul>
                                  <li>
                                    <p data-i18n="プロジェクト管理機能を追加しました。">プロジェクト管理機能を追加しました。</p>
                                  </li>
                                </ul>
                              </small>
                            </div>
                          </div>
                        </li>
                        <li class="list-group-item list-group-item-action dropdown-notifications-item">
                          <div class="d-flex">
                            <div class="flex-grow-1">
                              <h6 class="small mb-1">2025年5月21日</h6>
                              <small class="mb-1 d-block text-body">
                                <ul>
                                  <li>
                                    <p data-i18n="UIを変更しました。">UIを変更しました。</p>
                                  </li>
                                </ul>
                              </small>
                            </div>
                          </div>
                        </li>
                      </ul>
                    </li>
                  </ul>
                </li>
                <style>
                  #notification_list{
                    right: 0;
                  }
                  #notification_dot.badge-notifications {
                    display: none;
                    min-width: 1.1rem;
                    height: 1.1rem;
                    padding: 0 0.3rem;
                    font-size: 0.65rem;
                    line-height: 1.1rem;
                    align-items: center;
                    justify-content: center;
                  }
                </style>
                <!-- Notification -->
                <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2">
                  <a
                    id="open_electron_window_trigger"
                    class="nav-link hide-arrow btn btn-icon btn-text-secondary rounded-pill"
                    href="javascript:void(0);"
                    role="button"
                    aria-expanded="false">
                    <span class="position-relative">
                      <i class="icon-base ti tabler-bell icon-22px text-heading"></i>
                      <span class="badge rounded-pill bg-danger badge-notifications border badge_number" id="notification_dot" style="display: none;"></span>
                    </span>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end p-0" id="notification_list">
                    <li class="dropdown-menu-header border-bottom">
                      <div class="dropdown-header d-flex align-items-center py-3">
                        <h6 class="mb-0 me-auto" data-i18n="通知">通知</h6>
                        <div class="d-flex align-items-center h6 mb-0">
                          <span class="badge bg-label-primary me-2" id="notification_count"></span>
                        </div>
                      </div>
                    </li>
                    <li class="dropdown-notifications-list">
                      <!-- Tabs: 案件 / 総務 -->
                      <div class="d-flex align-items-center justify-content-between border-bottom">
                        <ul class="nav nav-tabs nav-fill border-0 flex-grow-1" role="tablist">
                        <li class="nav-item" role="presentation">
                          <button
                            class="nav-link active d-flex align-items-center justify-content-center h-100"
                            id="notification_tab_project_button"
                            data-bs-toggle="tab"
                            data-bs-target="#notification_tab_project"
                            type="button"
                            role="tab"
                            aria-controls="notification_tab_project"
                            aria-selected="true"
                          >
                            <span>案件</span>
                            <span class="badge badge-sm bg-label-primary ms-2 px-2" id="notification_count_project"></span>
                          </button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button
                            class="nav-link d-flex align-items-center justify-content-center h-100"
                            id="notification_tab_soumu_button"
                            data-bs-toggle="tab"
                            data-bs-target="#notification_tab_soumu"
                            type="button"
                            role="tab"
                            aria-controls="notification_tab_soumu"
                            aria-selected="false"
                          >
                            <span>総務</span>
                            <span class="badge badge-sm bg-label-primary ms-2 px-2" id="notification_count_soumu"></span>
                          </button>
                        </li>
                        </ul>
                        <a
                          href="javascript:void(0)"
                          class="dropdown-notifications-all p-2 btn btn-icon ms-2"
                          data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="すべて既読にする"
                          id="mark_all"
                          ><i class="icon-base ti tabler-mail-opened text-heading"></i
                        ></a>
                      </div>
                      <div class="tab-content p-0 scrollable-container">
                        <div
                          class="tab-pane fade show active"
                          id="notification_tab_project"
                          role="tabpanel"
                          aria-labelledby="notification_tab_project_button"
                        >
                          <ul class="list-group list-group-flush" id="notification_list_project">
                            <!-- JS will render project notifications here -->
                          </ul>
                        </div>
                        <div
                          class="tab-pane fade"
                          id="notification_tab_soumu"
                          role="tabpanel"
                          aria-labelledby="notification_tab_soumu_button"
                        >
                          <ul class="list-group list-group-flush" id="notification_list_soumu">
                            <!-- JS will render general (form/other) notifications here -->
                          </ul>
                        </div>
                      </div>
                    </li>
                    <!-- <li class="border-top">
                      <div class="d-grid p-4">
                        <a class="btn btn-primary btn-sm d-flex" href="javascript:void(0);">
                          <small class="align-middle" data-i18n="すべての通知を表示">すべての通知を表示</small>
                        </a>
                      </div>
                    </li> -->
                  </ul>
                </li>
                <!--/ Notification -->

                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a
                    class="nav-link dropdown-toggle hide-arrow p-0"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <div class="avatar" data-userid="<?=$_SESSION['userid']?>">
                      <?php if($_SESSION['user_image'] != '') {
                          echo '<img src="'.$root.'assets/upload/avatar/'.$_SESSION['user_image'].'" alt class="rounded-circle" />';
                        } else{
                          echo '<img src="'.$root.'assets/img/avatars/1.png" alt class="rounded-circle" />';
                       }?>
                    </div>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item mt-0" href="<?=$root?>member/view.php?id=<?=$_SESSION['id']?>">
                        <div class="d-flex align-items-center">
                          <div class="flex-shrink-0 me-2">
                            <div class="avatar" data-userid="<?=$_SESSION['userid']?>">
                              <?php if($_SESSION['user_image'] != '') {
                                echo '<img src="'.$root.'assets/upload/avatar/'.$_SESSION['user_image'].'" alt class="rounded-circle" />';
                              } else{
                                echo '<img src="'.$root.'assets/img/avatars/1.png" alt class="rounded-circle" />';
                              }
                              ?>
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            <h6 class="mb-0"><?=$_SESSION['realname']?></h6>
                            <!-- <small class="text-body-secondary"><?=$_SESSION['user_groupname']?></small> -->
                          </div>
                        </div>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider my-1 mx-n2"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="<?=$root?>member/view.php?id=<?=$_SESSION['id']?>">
                        <i class="icon-base ti tabler-user me-3 icon-md"></i
                        ><span class="align-middle" data-i18n="個人情報">個人情報</span>
                      </a>
                    </li>
                    <!-- <li>
                      <a class="dropdown-item" href="pages-account-settings-account.html">
                        <i class="icon-base ti tabler-settings me-3 icon-md"></i
                        ><span class="align-middle">Thiết lập tài khoản</span>
                      </a>
                    </li> -->
                    <!-- <li>
                      <a class="dropdown-item" href="pages-faq.html">
                        <i class="icon-base ti tabler-question-mark me-3 icon-md"></i
                        ><span class="align-middle">FAQ</span>
                      </a>
                    </li> -->
                    <li>
                      <div class="d-grid px-2 pt-2 pb-1">
                        <a class="btn btn-sm btn-danger d-flex" href="<?=$root?>logout.php"">
                          <small class="align-middle" data-i18n="ログアウト">ログアウト</small>
                          <i class="icon-base ti tabler-logout ms-2 icon-14px"></i>
                        </a>
                      </div>
                    </li>
                  </ul>
                </li>
                <!--/ User -->
              </ul>
            </div>
          </nav>

          <!-- / Navbar -->
          <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/quill/typography.css" />
          <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/quill/editor.css" />
          <!-- Todo Widget Offcanvas -->
          <div class="offcanvas offcanvas-bottom" tabindex="-1" id="offcanvasTodo" aria-labelledby="offcanvasTodoLabel" style="height: 80vh;">
            <div class="offcanvas-header border-bottom">
              <h5 class="offcanvas-title" id="offcanvasTodoLabel"><i class="fas fa-list-check me-2"></i><span data-i18n="Todo List">Todoリスト</span></h5>
              <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>
            </div>
            <div class="offcanvas-body" id="todoApp">
               <!-- Vue App will mount here -->
                   <div class="nav-align-top mb-4 h-100 d-flex flex-column">
                    <ul class="nav nav-tabs nav-fill" role="tablist">
                      <li class="nav-item">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'tasks' }" role="tab" aria-controls="navs-tasks" :aria-selected="activeTab === 'tasks'" @click="activeTab = 'tasks'">
                          <i class="fas fa-briefcase me-1"></i> <span data-i18n="マイタスク">マイタスク</span>
                          <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-danger ms-1" v-if="myTaskCount > 0">{{ myTaskCount }}</span>
                        </button>
                      </li>
                      <li class="nav-item">
                        <button type="button" class="nav-link" :class="{ active: activeTab === 'todos' }" role="tab" aria-controls="navs-todos" :aria-selected="activeTab === 'todos'" @click="activeTab = 'todos'">
                          <i class="fas fa-list me-1"></i> <span data-i18n="カスタムTodo">カスタムTodo</span>
                          <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-danger ms-1" v-if="incompleteTodoCount > 0">{{ incompleteTodoCount }}</span>
                        </button>
                      </li>
                    </ul>
                    <div class="tab-content flex-grow-1 overflow-auto pt-3 px-0">
                      <!-- My Tasks Tab -->
                      <div class="tab-pane fade" :class="{ 'show active': activeTab === 'tasks' }" id="navs-tasks" role="tabpanel">
                         <div v-if="loadingTasks" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                              <span class="visually-hidden" data-i18n="読み込み中...">読み込み中...</span>
                            </div>
                         </div>
                         <div v-else-if="tasks.length === 0" class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p data-i18n="割り当てられたタスクはありません">割り当てられたタスクはありません</p>
                         </div>
                         <div v-else class="my-task-table-wrap">
                            <table class="table table-sm table-hover align-middle mb-0 my-task-table">
                                <thead class="table-light">
                                    <tr>
                                        <th class="my-task-col-project"><span data-i18n="案件">案件</span></th>
                                        <th class="my-task-col-construction"><span data-i18n="工事番号">工事番号</span></th>
                                        <th class="my-task-col-title"><span data-i18n="タスク">タスク</span></th>
                                        <th class="my-task-col-kind"><span data-i18n="種別">種別</span></th>
                                        <th class="my-task-col-drawing"><span data-i18n="図面">図面</span></th>
                                        <th class="my-task-col-priority"><span data-i18n="優先度">優先度</span></th>
                                        <th class="my-task-col-period"><span data-i18n="期限">期限</span></th>
                                        <th class="my-task-col-assignee"><span data-i18n="担当者">担当者</span></th>
                                        <th class="my-task-col-ack"><span data-i18n="受領">受領</span></th>
                                        <th class="my-task-col-creator"><span data-i18n="作成者">作成者</span></th>
                                        <th class="my-task-col-status"><span data-i18n="ステータス">ステータス</span></th>
                                        <th class="my-task-col-progress"><span data-i18n="進捗">進捗</span></th>
                                        <th class="my-task-col-workload"><span data-i18n="工数">工数</span></th>
                                        <th class="my-task-col-note"><span data-i18n="メモ">メモ</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="task in tasks" :key="task.id">
                                        <td>
                                            <a :href="'/project/detail.php?id=' + task.project_id" class="text-decoration-none small">
                                                <span class="badge bg-label-primary me-1">#{{ task.project_id }}</span>
                                                <span class="text-truncate d-inline-block my-task-project-name">{{ task.project_name }}</span>
                                            </a>
                                        </td>
                                        <td>
                                            <a :href="'/project/detail.php?id=' + task.project_id" class="text-decoration-none small">
                                                <span class="text-nowrap">{{ task.project_construction_number || '-' }}</span>
                                            </a>
                                        </td>
                                        <td>
                                            <a :href="'/project/task.php?project_id=' + task.project_id + '&task_id=' + task.id" class="text-decoration-none fw-bold small">
                                                <span class="badge bg-label-secondary me-1">#{{ task.id }}</span>
                                                <span class="text-truncate d-inline-block my-task-title">{{ task.title }}</span>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge small" :class="getTaskKindBadgeClass(task.task_kind)">{{ getTaskKindLabel(task.task_kind) }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 flex-wrap small">
                                                <template v-if="isDrawingLinkVisibleForTask(task)">
                                                    <div class="form-check mb-0" :title="t('図面リストに追加', '図面リストに追加')">
                                                        <input type="checkbox" class="form-check-input" :id="'mytask-drawing-link-' + task.id"
                                                            :checked="isTaskLinkedToDrawings(task)"
                                                            :disabled="!canEditDrawingLink(task)"
                                                            @change="toggleTaskDrawingLink(task, $event)">
                                                    </div>
                                                    <input v-if="isTaskLinkedToDrawings(task) && canEditDrawingLink(task)"
                                                        type="number"
                                                        class="form-control form-control-sm my-task-drawing-count-input"
                                                        :class="{ 'my-task-drawing-count-input--loading': isDrawingCountSaving(task.id) }"
                                                        min="1"
                                                        step="1"
                                                        :value="task.drawing_count > 0 ? task.drawing_count : 1"
                                                        :disabled="isDrawingCountSaving(task.id)"
                                                        @change="saveTaskDrawingCount(task, $event.target.value)">
                                                    <span v-else-if="isTaskLinkedToDrawings(task)" class="text-nowrap">{{ task.drawing_count }}</span>
                                                </template>
                                                <span v-else class="text-muted">—</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge" :class="'bg-' + getPriorityColor(task.priority)">{{ getPriorityLabel(task.priority) }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <small class="text-nowrap text-muted">{{ formatDate(task.due_date) }}</small>
                                                <span v-if="task.status !== 'completed' && task.status !== 'cancelled' && getTimeRemainingForDue(task.due_date)" :class="['badge badge-sm', getTimeRemainingForDue(task.due_date).class]" style="font-size: 0.7rem;">
                                                    {{ getTimeRemainingForDue(task.due_date).text }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <template v-for="assignee in getTaskAssignees(task)" :key="'assignee-' + task.id + '-' + assignee.id">
                                                    <span class="avatar" :title="assignee.realname">
                                                        <img v-if="getUserAvatarSrc(assignee)" class="rounded-circle" :src="getUserAvatarSrc(assignee)" :alt="assignee.realname" width="24" height="24">
                                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getUserInitials(assignee.realname) }}</span>
                                                    </span>
                                                </template>
                                                <span v-if="getTaskAssignees(task).length === 0" class="text-muted small">—</span>
                                            </div>
                                        </td>
                                        <td>
                                            <button v-if="!isAcknowledged(task)" type="button" class="btn btn-sm btn-outline-success" @click="acknowledgeTask(task)" title="受領">
                                                <i class="fas fa-check me-1"></i><span data-i18n="受領">受領</span>
                                            </button>
                                            <span v-else class="badge bg-success" title="受領済み"><i class="fas fa-check me-1"></i><span data-i18n="受領済み">受領済み</span></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1" v-if="getTaskCreator(task)">
                                                <span class="avatar" :title="getTaskCreator(task).realname">
                                                    <img v-if="getUserAvatarSrc(getTaskCreator(task))" class="rounded-circle" :src="getUserAvatarSrc(getTaskCreator(task))" :alt="getTaskCreator(task).realname" width="24" height="24">
                                                    <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getUserInitials(getTaskCreator(task).realname) }}</span>
                                                </span>
                                            </div>
                                            <span v-else class="text-muted small">—</span>
                                        </td>
                                        <td>
                                            <div class="btn-group my-task-status-dropdown">
                                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                                        :class="getStatusButtonClass(task.status)"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false">
                                                    {{ getStatusLabel(task.status) }}
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li v-for="status in taskStatuses" :key="status.value" class="dropdown-item" style="cursor:pointer" @click="updateTaskStatus(task, status.value)">
                                                        {{ t(status.i18nKey || status.label, status.label) }}
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" style="min-width: 72px;" :value="task.progress != null ? task.progress : 0" @change="updateTaskProgress(task, parseInt($event.target.value, 10))">
                                                <option v-for="p in progressOptions" :key="p" :value="p">{{ p }}%</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1 task-workload-cell">
                                                <template v-if="canEditTaskWorkload(task)">
                                                    <span
                                                        class="task-workload-input-shell"
                                                        :class="{ 'task-workload-input-shell--timer-active': hasActiveTaskTimer(task) }">
                                                        <input
                                                            type="text"
                                                            readonly
                                                            tabindex="-1"
                                                            class="form-control form-control-sm task-workload-input task-workload-input--readonly my-task-workload-input"
                                                            :value="formatWorkloadPickerDisplay(task.estimated_hours)"
                                                            placeholder="0h"
                                                            :class="{ 'task-workload-input--loading': isEstimatedHoursSaving(task.id) }"
                                                            :disabled="isEstimatedHoursSaving(task.id)"
                                                            @click="openWorkloadModal(task)">
                                                    </span>
                                                </template>
                                                <span
                                                    v-else
                                                    class="task-workload-input-shell text-nowrap"
                                                    :class="{ 'task-workload-input-shell--timer-active': hasActiveTaskTimer(task) }">
                                                    <span class="small text-nowrap task-workload-display">{{ formatEstimatedHours(task.estimated_hours) }}</span>
                                                </span>
                                                <button
                                                    v-if="canTrackTaskTime(task)"
                                                    type="button"
                                                    class="btn btn-sm task-timer-btn"
                                                    :class="isTaskTimerActive(task.id) ? 'btn-danger task-timer-btn--active' : 'btn-outline-success'"
                                                    :title="isTaskTimerActive(task.id) ? t('作業時間を終了', '作業時間を終了') : t('作業時間を開始', '作業時間を開始')"
                                                    :disabled="isTaskTimerToggling(task.id)"
                                                    @click="toggleTaskTimer(task)">
                                                    <i :class="isTaskTimerActive(task.id) ? 'fa fa-stop' : 'fa fa-play'"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="my-task-note-cell" @click="openTaskNoteModal(task)" :title="getTaskNoteSnippet(task.note) || t('メモ', 'メモ')">
                                                <span v-if="getTaskNoteSnippet(task.note)" class="small text-truncate d-inline-block my-task-note">{{ getTaskNoteSnippet(task.note) }}</span>
                                                <i v-else class="fa fa-sticky-note text-muted"></i>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                         </div>
                      </div>

                      <!-- Custom Todos Tab -->
                      <div class="tab-pane fade" :class="{ 'show active': activeTab === 'todos' }" id="navs-todos" role="tabpanel">
                        <div class="input-group mb-3">
                          <input type="text" class="form-control" v-model="newTodo.title" :placeholder="t('Add a new todo...', '新しいTodoを追加...')" data-i18n="Add a new todo..." @keyup.enter="addTodo">
                          
                          <!-- Priority Select -->
                          <select class="form-select" v-model="newTodo.priority" style="max-width: 220px;">
                              <option value="10" data-i18n="優先度: 低">低</option>
                              <option value="50" data-i18n="優先度: 中">中</option>
                              <option value="100" data-i18n="優先度: 高">高</option>
                          </select>

                          <!-- Deadline Input -->
                          <input type="text" class="form-control flatpickr-input" id="new-todo-date" :placeholder="t('期限')" data-i18n="期限" style="max-width: 200px;">
                          
                          <button class="btn btn-primary" type="button" @click="addTodo">
                            <i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline" data-i18n="追加">追加</span>
                          </button>
                          <button v-if="todos.some(t => t.todo_complete == 1)" type="button" class="btn btn-outline-danger ms-2" @click="deleteCompletedTodos" title="完了済みのTodoを削除">
                            <i class="fas fa-trash-alt me-1"></i><span data-i18n="完了済みを削除">完了済みを削除</span>
                          </button>
                        </div>

                        <div v-if="loadingTodos" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                              <span class="visually-hidden" data-i18n="Loading...">読み込み中...</span>
                            </div>
                         </div>
                         <div v-else-if="todos.length === 0" class="text-center text-muted py-5">
                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                            <p data-i18n="Todoはありません">Todoはありません</p>
                         </div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 36px;" class="text-center"><span data-i18n="完了">完了</span></th>
                                        <th style="width: 30%;"><span data-i18n="Todo">Todo</span></th>
                                        <th><span data-i18n="優先度">優先度</span></th>
                                        <th><span data-i18n="期限">期限</span></th>
                                        <th style="min-width: 100px;"><span data-i18n="Link">リンク</span></th>
                                        <th style="min-width: 120px;"><span data-i18n="備考">備考</span></th>
                                        <th style="width: 80px;"><span data-i18n="操作">操作</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="todo in todos" :key="todo.id" :class="{'table-secondary': todo.todo_complete == 1}">
                                        <td class="align-middle">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" :checked="todo.todo_complete == 1" @change="toggleTodo(todo)">
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <input v-if="editingTodoId === todo.id" ref="editTodoInput" type="text" class="form-control form-control-sm" v-model="editingTodoTitle" @keyup.enter="saveTodoEdit(todo)" @keyup.esc="cancelEditTodo">
                                            <span v-else :class="{'text-decoration-line-through text-muted': todo.todo_complete == 1}" :style="todo.todo_complete != 1 ? { cursor: 'pointer' } : {}" @click="startEditTodo(todo)" title="クリックして編集">{{ todo.todo_title }}</span>
                                        </td>
                                        <td class="align-middle">
                                            <select v-if="editingTodoId === todo.id" class="form-select form-select-sm" v-model.number="editingTodoPriority" style="width: auto; min-width: 70px;">
                                                <option :value="10">低</option>
                                                <option :value="50">中</option>
                                                <option :value="100">高</option>
                                            </select>
                                            <span v-else class="badge" :class="'bg-' + getTodoPriorityColor(todo.todo_priority)">{{ getTodoPriorityLabel(todo.todo_priority) }}</span>
                                        </td>
                                        <td class="align-middle">
                                            <template v-if="editingTodoId === todo.id">
                                                <input ref="editTodoDateInput" type="text" class="form-control form-control-sm" readonly :placeholder="t('期限')" style="max-width: 180px;">
                                            </template>
                                            <template v-else>
                                                <div v-if="todo.todo_term" class="d-flex flex-start gap-1">
                                                    <small class="text-nowrap text-muted">{{ formatDate(todo.todo_term) }}</small>
                                                    <span v-if="todo.todo_complete != 1 && getTimeRemainingForDue(todo.todo_term)" :class="['badge badge-sm', getTimeRemainingForDue(todo.todo_term).class]" style="font-size: 0.7rem;">
                                                        {{ getTimeRemainingForDue(todo.todo_term).text }}
                                                    </span>
                                                </div>
                                                <span v-else class="text-muted">-</span>
                                            </template>
                                        </td>
                                        <td class="align-middle">
                                            <template v-if="editingTodoId === todo.id">
                                                <input type="text" class="form-control form-control-sm" v-model="editingTodoLink" :placeholder="t('Link', 'リンク')" style="min-width: 100px;">
                                            </template>
                                            <template v-else>
                                                <a v-if="todo.todo_link" :href="todo.todo_link" target="_blank" rel="noopener" class="small text-truncate d-inline-block" style="max-width: 150px;" :title="todo.todo_link">{{ todo.todo_link }}</a>
                                                <span v-else class="text-muted">-</span>
                                            </template>
                                        </td>
                                        <td class="align-middle">
                                            <template v-if="editingTodoId === todo.id">
                                                <textarea class="form-control form-control-sm" v-model="editingTodoComment" rows="2" :placeholder="t('備考', '備考')" style="min-width: 120px; resize: vertical;"></textarea>
                                            </template>
                                            <template v-else>
                                                <span v-if="todo.todo_comment" class="small text-break" style="max-width: 200px; display: inline-block; white-space: pre-wrap;">{{ todo.todo_comment }}</span>
                                                <span v-else class="text-muted">-</span>
                                            </template>
                                        </td>
                                        <td class="align-middle">
                                          <div class="d-flex gap-1">
                                            <button v-if="editingTodoId === todo.id" class="btn btn-sm btn-success me-1 text-nowrap" @click="saveTodoEdit(todo)" title="保存">
                                                <i class="fas fa-check me-1"></i> <span data-i18n="保存">保存</span>
                                            </button>
                                            <button v-if="editingTodoId === todo.id" class="btn btn-sm btn-secondary me-1 text-nowrap" @click="cancelEditTodo" title="キャンセル">
                                                <i class="fas fa-times me-1"></i> <span data-i18n="キャンセル">キャンセル</span>
                                            </button>
                                            <button v-if="editingTodoId !== todo.id && todo.todo_complete != 1" class="btn btn-sm btn-outline-primary me-1 text-nowrap" @click="startEditTodo(todo)" title="編集">
                                                <i class="fas fa-pen me-1"></i> <span data-i18n="編集">編集</span>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger text-nowrap" @click="deleteTodo(todo)" title="削除">
                                                <i class="fas fa-trash me-1"></i> <span data-i18n="削除">削除</span>
                                            </button>
                                          </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                      </div>
                    </div>
                   </div>

            <!-- Workload Modal (same as project/task.php) -->
            <div class="modal fade task-workload-modal" tabindex="-1" :class="{ show: workloadModal.show }" style="display: block;" v-if="workloadModal.show">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><span data-i18n="工数を編集">工数を編集</span></h5>
                            <button type="button" class="btn-close" @click="closeWorkloadModal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2 align-items-end">
                                <div class="col">
                                    <label class="form-label small mb-1"><span data-i18n="時間">時間</span></label>
                                    <input type="number" class="form-control" min="0" step="1" v-model.number="workloadModal.hours" @keyup.enter="confirmWorkloadModal">
                                </div>
                                <div class="col-auto pb-2 text-muted">:</div>
                                <div class="col">
                                    <label class="form-label small mb-1"><span data-i18n="分">分</span></label>
                                    <input type="number" class="form-control" min="0" step="1" v-model.number="workloadModal.minutes" @keyup.enter="confirmWorkloadModal">
                                </div>
                            </div>
                            <p class="small text-muted mt-2 mb-0">
                                <span data-i18n="換算">換算</span>: {{ getWorkloadModalPreview() }}
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeWorkloadModal"><span data-i18n="キャンセル">キャンセル</span></button>
                            <button type="button" class="btn btn-primary" @click="confirmWorkloadModal" :disabled="workloadModal.saving || isEstimatedHoursSaving(workloadModal.taskId)">
                                <span data-i18n="保存">保存</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Note Modal (same as project/task.php) -->
            <div class="modal fade task-note-modal" tabindex="-1" :class="{ show: showTaskNoteModal }" style="display: block;" v-if="showTaskNoteModal">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><span data-i18n="メモ">メモ</span></h5>
                            <button type="button" class="btn-close" @click="closeTaskNoteModal"></button>
                        </div>
                        <div class="modal-body">
                            <div v-if="!taskNoteModal.canEdit">
                                <label class="form-label"><span data-i18n="内容">内容</span></label>
                                <div class="form-control ql-editor" style="min-height:120px;max-height:480px;overflow-y:auto;" v-html="taskNoteModal.content || '-'"></div>
                            </div>
                            <form v-else @submit.prevent="saveTaskNote">
                                <div class="mb-0">
                                    <label class="form-label"><span data-i18n="内容">内容</span></label>
                                    <div class="custom_editor">
                                        <div class="custom_editor_content" id="quill_mytask_note_content"></div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button v-if="taskNoteModal.canEdit && (taskNoteModal.content || quillTaskNoteContent)" type="button" class="btn btn-danger me-auto" @click="clearTaskNote">
                                <i class="fa fa-trash me-1"></i><span data-i18n="削除">削除</span>
                            </button>
                            <button type="button" class="btn btn-secondary" @click="closeTaskNoteModal"><span data-i18n="キャンセル">キャンセル</span></button>
                            <button v-if="taskNoteModal.canEdit" type="button" class="btn btn-primary" @click="saveTaskNote" :disabled="!((quillTaskNoteContent && quillTaskNoteContent.trim()) || (taskNoteModal.content && taskNoteModal.content.trim()))">
                                <i class="fa fa-save me-1"></i><span data-i18n="保存">保存</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
          </div>
          <style>
            #offcanvasTodo{
              z-index: 9999;
            }
            .task-note-modal {
              z-index: 10050;
              background: rgba(0, 0, 0, 0.5);
            }
            .task-workload-modal {
              z-index: 10050;
              background: rgba(0, 0, 0, 0.5);
            }
            .my-task-workload-input {
              width: 4.5rem;
              min-width: 3.5rem;
              max-width: 5.5rem;
              padding: 0.25rem 0.4rem;
              font-size: 0.8125rem;
              text-align: center;
            }
            .my-task-note-cell {
              cursor: pointer;
              max-width: 100%;
              line-height: 1.3;
            }
            .my-task-note-cell:hover .fa-sticky-note {
              color: var(--bs-primary) !important;
            }
            .my-task-table-wrap .table-sm > :not(caption) > * > *{
              padding: 0.594rem .25rem;
            }
            .my-task-status-dropdown {
              position: relative;
            }
            .my-task-status-dropdown .dropdown-menu[data-bs-popper] {
              position: absolute !important;
            }
            .my-task-table {
              min-width: 1100px;
              font-size: 0.8125rem;
            }
            .my-task-table th,
            .my-task-table td {
              white-space: nowrap;
              vertical-align: middle;
            }
            .my-task-col-project { min-width: 8rem; }
            .my-task-col-construction { min-width: 7rem; }
            .my-task-col-title { min-width: 10rem; }
            .my-task-col-kind { min-width: 5.5rem; }
            .my-task-col-drawing { min-width: 8rem; }
            .my-task-drawing-count-input {
              width: 4rem;
              min-width: 4rem;
              padding: 0.4rem 0.5rem;
              font-size: 0.95rem;
              line-height: 1.35;
            }
            .my-task-drawing-count-input--loading {
              animation: my-task-drawing-count-border-pulse 0.9s ease-in-out infinite;
              pointer-events: none;
            }
            @keyframes my-task-drawing-count-border-pulse {
              0%, 100% {
                border-color: var(--bs-primary, #696cff);
                box-shadow: 0 0 0 0 rgba(105, 108, 255, 0.45);
              }
              50% {
                border-color: var(--bs-primary, #696cff);
                box-shadow: 0 0 0 3px rgba(105, 108, 255, 0.25);
              }
            }
            .my-task-col-priority { min-width: 4rem; }
            .my-task-col-period { min-width: 7rem; }
            .my-task-col-assignee,
            .my-task-col-creator { min-width: 3.5rem; }
            .my-task-col-ack { min-width: 5rem; }
            .my-task-col-status { min-width: 6.5rem; }
            .my-task-status-dropdown .btn {
              min-width: 5.5rem;
              text-align: left;
            }
            .my-task-status-dropdown .dropdown-menu {
              min-width: 6.5rem;
            }
            .my-task-col-progress { min-width: 5rem; }
            .my-task-col-workload { min-width: 7rem; }
            .my-task-col-note { min-width: 5rem; max-width: 8rem; }
            .my-task-project-name,
            .my-task-title {
              max-width: 10rem;
              vertical-align: bottom;
            }
            .my-task-note {
              max-width: 7rem;
            }
            .my-task-table .avatar-xs {
              width: 24px;
              height: 24px;
            }
            .my-task-table .avatar-xs .avatar-initial {
              width: 24px;
              height: 24px;
              font-size: 0.65rem;
            }
          </style>

          <span class="app-version" style="background-color: #ccc; padding: 5px; border-radius: 5px; position: fixed; bottom: 10px; left: 10px; font-size: 10px; color: #000; z-index: 2000;">v<?=APP_VERSION?></span>

          <!-- Task Timer Widget - bottom right, left of Todo button -->
          <div id="global-task-timer-nav" class="global-task-timer-fab" style="display: none;" aria-live="polite">
            <div class="global-task-timer-widget">
              <span class="global-task-timer-time me-1">
                <span class="">
                <i class="fa fa-stopwatch text-success"></i>
                <span id="global-task-timer-label" class="global-task-timer-label" data-i18n="作業計測中">作業計測中</span>
                </span>
                <span id="global-task-timer-display" class="font-monospace">00:00:00</span>
              </span>
              <i class="fas fa-arrow-right"></i><a href="javascript:void(0);" id="global-task-timer-task-link" class="global-task-timer-task-link"></a>
              <button type="button" class="btn btn-sm btn-danger btn-icon rounded-circle global-task-timer-stop-btn" id="global-task-timer-stop-btn" title="作業時間を終了" aria-label="作業時間を終了">
                <i class="fa fa-stop"></i>
              </button>
            </div>
          </div>

          <script>
            window.__APP_ROOT = <?= json_encode($root ?? ROOT) ?>;
            window.__COMMAND_PALETTE_ENABLED = true;
          </script>

          <!-- Command Palette Modal -->
          <div class="modal fade" id="commandPaletteModal" tabindex="-1" aria-labelledby="commandPaletteModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="commandPaletteModalLabel">
                    <i class="fas fa-keyboard me-2"></i><span data-i18n="コマンドパレット">コマンドパレット</span>
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-3">
                    <div class="btn-group btn-group-sm mb-2 command-palette-mode-group" role="group">
                      <button type="button" class="btn btn-outline-primary active" data-command-palette-mode="parent" data-i18n="建物を検索">建物を検索</button>
                      <button type="button" class="btn btn-outline-primary" data-command-palette-mode="project" data-i18n="案件を検索">案件を検索</button>
                      <?php if (($_SESSION['group'] ?? '') != '7' && ($_SESSION['group'] ?? '') != '6'): ?>
                      <button type="button" class="btn btn-outline-primary" data-command-palette-mode="customer" data-i18n="顧客を検索">顧客を検索</button>
                      <?php endif; ?>
                    </div>
                    <input type="text" class="form-control" id="command-palette-search" autocomplete="off"
                      placeholder="工事番号 / ID / 会社名 / 支店名" data-i18n="工事番号 / ID / 会社名 / 支店名">
                    <div id="command-palette-results" class="command-palette-results mt-2 border rounded"></div>
                    <div id="command-palette-empty" class="text-muted small mt-2 d-none" data-i18n="検索結果がありません">検索結果がありません</div>
                  </div>
                  <hr class="my-3">
                  <h6 class="mb-2" data-i18n="ショートカット一覧">ショートカット一覧</h6>
                  <div id="command-palette-shortcuts"></div>
                </div>
              </div>
            </div>
          </div>

          <?php if (($_SESSION['group'] ?? '') != '7' && ($_SESSION['group'] ?? '') != '6'): ?>
          <?php require_once DIR_VIEW . 'customer-global-modal.php'; ?>
          <?php endif; ?>

          <button type="button" id="command-palette-toggle" class="btn btn-primary rounded-circle position-fixed waves-effect waves-light"
            title="コマンドパレット (F1 / Ctrl+K)" data-bs-toggle="tooltip" data-bs-placement="left">
            <i class="fas fa-keyboard"></i>
          </button>

          <!-- Todo Toggle Button (same style as AI Chat button) -->
          <button data-bs-toggle="offcanvas" data-bs-target="#offcanvasTodo" id="todo-toggle" class="btn btn-primary rounded-circle position-fixed waves-effect waves-light">
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="todo-badge"></span>
            <i class="fas fa-list-check"></i>
          </button>

          <!-- AI Chat Widget -->
          <div class="modal fade" id="modalAI" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-simple modal-dialog-centered modal-chat-w">
              <div class="modal-content p-0">
                <div class="modal-body1">
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                  <?php $view = new View(); ?>
                  <?php $view->chat(); ?>
                </div>
              </div>
            </div>
          </div>

          <button data-bs-toggle="modal" data-bs-target="#modalAI" id="ai-chat-toggle" class="btn btn-primary rounded-circle position-fixed"><i class="icon-base ti tabler-message-circle-2 icon-md"></i></button>
          <?php if (!empty($__showProjectDisplayTimezone)): ?>
          <script>
          (function () {
            function getDisplayTimezoneI18nKey() {
              var lang = (typeof i18next !== 'undefined' && i18next.language) ? String(i18next.language) : 'en';
              return lang.indexOf('vi') === 0 ? '表示: GMT+7 (Việt Nam)' : '表示: JST (日本)';
            }
            function updateNavDisplayTimezone() {
              var textEl = document.getElementById('nav-display-timezone-text');
              if (!textEl) return;
              var key = getDisplayTimezoneI18nKey();
              textEl.setAttribute('data-i18n', key);
              textEl.textContent = (typeof i18next !== 'undefined' && i18next.isInitialized)
                ? i18next.t(key)
                : key;
              var badge = document.getElementById('nav-display-timezone-badge');
              if (badge) badge.title = textEl.textContent;
            }
            function scheduleUpdate() {
              if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                updateNavDisplayTimezone();
              } else {
                setTimeout(scheduleUpdate, 100);
              }
            }
            if (document.readyState === 'loading') {
              document.addEventListener('DOMContentLoaded', scheduleUpdate);
            } else {
              scheduleUpdate();
            }
          })();
          </script>
          <?php endif; ?>
          <!-- Content wrapper -->
          <div class="content-wrapper">
