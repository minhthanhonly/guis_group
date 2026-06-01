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
                <li class="menu-item <?php if($directory == 'project' && $page != 'project_gantt' && $page != 'custom_fields' && $page != 'task_overview' && $page != 'employee_statistics' && $page != 'team_revenue_targets') echo 'active'; ?>">
                  <a href="<?=$root?>project/" class="menu-link">
                    <div data-i18n="案件一覧">案件一覧</div>
                  </a>
                </li>
                <!-- <li class="menu-item <?php if($directory == 'project' && $page == 'mytask') echo 'active'; ?>">
                  <a href="<?=$root?>project/mytask.php" class="menu-link">
                    <div data-i18n="マイタスク">マイタスク</div>
                  </a>
                </li> -->
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
                
                <?php if($_SESSION['isProjectManager']){ ?>
                  <li class="menu-item <?php if($directory == 'project' && $page == 'custom_fields') echo 'active'; ?>">
                    <a href="<?=$root?>project/custom_fields.php" class="menu-link">
                      <div data-i18n="カスタムフィールド">カスタムフィールド</div>
                    </a>
                  </li>
                  <li class="menu-item <?php if($directory == 'price_list') echo 'active'; ?>">
                    <a href="<?=$root?>price_list" class="menu-link">
                      <div data-i18n="価格表管理">価格表管理</div>
                    </a>
                  </li>
                 
                <?php } ?>

                <?php if($_SESSION['authority'] == 'administrator' && $_SESSION['group'] != '7'  && $_SESSION['group'] != '6'){?>
                  <li class="menu-item <?php if($directory == 'project' && $page == 'employee_statistics') echo 'active'; ?>">
                    <a href="<?=$root?>project/employee_statistics.php" class="menu-link">
                      <div data-i18n="従業員統計">従業員統計</div>
                    </a>
                  </li>
                  <li class="menu-item <?php if($directory == 'project' && $page == 'team_revenue_targets') echo 'active'; ?>">
                    <a href="<?=$root?>project/team_revenue_targets.php" class="menu-link">
                      <div data-i18n="チーム売上目標設定">チーム売上目標設定</div>
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
             
           <li class="menu-item <?php if($directory == 'form') echo 'active open'; ?>">
              <a href="<?=$root?>form/index.php" class="menu-link">
                <i class="menu-icon icon-base fa fa-file-alt"></i>
                <div data-i18n="申請・承認">申請・承認</div>
                <?php if ($form_pending_badge > 0) { ?><span class="badge badge_number bg-warning text-dark rounded-pill ms-auto"><?= $form_pending_badge ?></span><?php } ?>
                <span id="form-unread-comment-badge" class="badge badge_number bg-danger rounded-pill ms-1<?= ($form_unread_comment_badge > 0 ? '' : ' d-none') ?>"><?= intval($form_unread_comment_badge) ?></span>
              </a>
            </li>
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
            <?php if($_SESSION['show_project'] == 1){ ?>
            <li class="menu-item <?php if($directory == 'customer') echo 'active open'; ?>">
              <a href="<?=$root?>customer/" class="menu-link">
                <i class="menu-icon icon-base fa fa-users"></i>
                <div data-i18n="顧客情報">顧客情報</div>
              </a>
            </li>
            <?php } ?>
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
                  <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
                    <span class="d-inline-block text-body-secondary fw-normal" id="autocomplete"></span>
                  </a>
                </div>
              </div>

              <!-- /Search -->

              <ul class="navbar-nav flex-row align-items-center ms-md-auto">
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
                      <span class="badge rounded-pill bg-danger badge-dot badge-notifications border" id="notification_dot" style="display: none;"></span>
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
                    <div class="tab-content flex-grow-1 overflow-auto pt-3">
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
                         <div v-else class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><span data-i18n="案件">案件</span></th>
                                        <th><span data-i18n="タスク">タスク</span></th>
                                        <th><span data-i18n="受領">受領</span></th>
                                        <th><span data-i18n="ステータス">ステータス</span></th>
                                        <th><span data-i18n="優先度">優先度</span></th>
                                        <th><span data-i18n="進捗">進捗</span></th>
                                        <th><span data-i18n="期限">期限</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="task in tasks" :key="task.id">
                                        <td>
                                            <a :href="'/project/detail.php?id=' + task.project_id" class="text-decoration-none small">
                                                <span class="badge bg-label-primary me-1">#{{ task.project_id }}</span>
                                                {{ task.project_name }}
                                            </a>
                                        </td>
                                        <td>
                                            <a :href="'/project/task.php?project_id=' + task.project_id + '&task_id=' + task.id" class="text-decoration-none fw-bold small">
                                                <span class="badge bg-label-secondary me-1">#{{ task.id }}</span>
                                                {{ task.title }}
                                            </a>
                                        </td>
                                        <td>
                                            <button v-if="!isAcknowledged(task)" type="button" class="btn btn-sm btn-outline-success" @click="acknowledgeTask(task)" title="受領">
                                                <i class="fas fa-check me-1"></i><span data-i18n="受領">受領</span>
                                            </button>
                                            <span v-else class="badge bg-success" title="受領済み"><i class="fas fa-check me-1"></i><span data-i18n="受領済み">受領済み</span></span>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" style="min-width: 90px;" :value="task.status" @change="updateTaskStatus(task, $event.target.value)">
                                                <option v-for="s in taskStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                            </select>
                                        </td>
                                        <td>
                                            <span class="badge" :class="'bg-' + getPriorityColor(task.priority)">{{ getPriorityLabel(task.priority) }}</span>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm"  :value="task.progress != null ? task.progress : 0" @change="updateTaskProgress(task, parseInt($event.target.value, 10))">
                                                <option v-for="p in [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100]" :key="p" :value="p">{{ p }}%</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-start gap-1">
                                                <small class="text-nowrap text-muted">{{ formatDate(task.due_date) }}</small>
                                                <span v-if="task.status !== 'completed' && task.status !== 'cancelled' && getTimeRemainingForDue(task.due_date)" :class="['badge badge-sm', getTimeRemainingForDue(task.due_date).class]" style="font-size: 0.7rem;">
                                                    {{ getTimeRemainingForDue(task.due_date).text }}
                                                </span>
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
            </div>
          </div>
          <style>
            #offcanvasTodo{
              z-index: 9999;
            }
          </style>

          <span class="app-version" style="background-color: #ccc; padding: 5px; border-radius: 5px; position: fixed; bottom: 10px; left: 10px; font-size: 10px; color: #000; z-index: 2000;">v<?=APP_VERSION?></span>

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
          <!-- Content wrapper -->
          <div class="content-wrapper">
