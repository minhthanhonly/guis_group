<!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->
        <aside id="layout-menu" class="layout-menu menu-vertical menu">
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
                    <div data-i18n="ガントチャート">ガントチャート</div>
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

           
              <li class="menu-item <?php if($directory == 'schedule') echo 'active open'; ?>">
                <a href="<?=$root?>schedule/" class="menu-link">
                  <i class="menu-icon icon-base ti tabler-calendar-event"></i>
                  <div data-i18n="カレンダー">カレンダー</div>
                </a>
              </li>
            <?php if($_SESSION['group'] != '7' && $_SESSION['group'] != '6' && $_SESSION['show_project'] == 1){ ?>
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
            

            <!-- <li class="menu-item <?php if($directory == 'form') echo 'active open'; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-settings"></i>
                <div data-i18n="申請・承認">申請・承認</div>
              </a>

              <ul class="menu-sub">
                <li class="menu-item <?php echo $active1; ?>">
                  <a href="<?=$root?>form/index.php" class="menu-link">
                    <div data-i18n="休職">休職</div>
                  </a>
                </li>
              </ul>
            </li> -->

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
        </aside>

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

                <!-- Notification -->
                <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-2">
                  <a
                    class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown"
                    data-bs-auto-close="outside"
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
                          <a
                            href="javascript:void(0)"
                            class="dropdown-notifications-all p-2 btn btn-icon"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Mark all as read"
                            id="mark_all"
                            ><i class="icon-base ti tabler-mail-opened text-heading"></i
                          ></a>
                        </div>
                      </div>
                    </li>
                    <li class="dropdown-notifications-list scrollable-container">
                      <ul class="list-group list-group-flush">
                        <li class="list-group-item list-group-item-action dropdown-notifications-item marked-as-read">
                          <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                              <div class="avatar">
                                <img src="<?=$root?>assets/img/avatars/1.png" alt class="rounded-circle" />
                              </div>
                            </div>
                            <div class="flex-grow-1">
                              <h6 class="small mb-1" data-i18n="通知はありません">通知はありません 🎉</h6>
                              <!-- <small class="text-body-secondary">1h ago</small> -->
                            </div>
                            <div class="flex-shrink-0 dropdown-notifications-actions">
                              <a href="javascript:void(0)" class="dropdown-notifications-read"
                                ><span class="badge badge-dot"></span
                              ></a>
                              <a href="javascript:void(0)" class="dropdown-notifications-archive"
                                ><span class="icon-base ti tabler-x"></span
                              ></a>
                            </div>
                          </div>
                        </li>
                       
                      </ul>
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

            