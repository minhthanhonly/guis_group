<!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <!-- Menu -->

        <aside id="layout-menu" class="layout-menu menu-vertical menu">
          <div class="app-brand demo">
            <a href="/" class="app-brand-link">
              <span class="app-brand-logo demo">
                <span class="text-primary">
                  <img src="<?=ROOT?>assets/img/<?=APP_LOGO?>" alt="" width="30"
                  data-app-light-img="<?=APP_LOGO?>"
                  data-app-dark-img="<?=APP_LOGO_DARK?>" />
                </span>
              </span>
              <span class="app-brand-text demo menu-text fw-bold ms-3"><?=APP_NAME?></span>
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
              <i class="icon-base ti tabler-x d-block d-xl-none"></i>
            </a>
          </div>

          <div class="menu-inner-shadow"></div>
          <ul class="menu-inner py-1">
            <!-- Dashboards -->
            <li class="menu-item <?php if($directory == 'top') echo 'active'; ?>">
              <a href="<?=$root?>" class="menu-link">
                <i class="menu-icon icon-base ti tabler-home"></i>
                <div data-i18n="ホーム">ホーム</div>
                <!-- <div class="badge text-bg-danger rounded-pill ms-auto">5</div> -->
              </a>
            </li>

            <!-- Layouts -->
            <li class="menu-item <?php if($directory == 'project') echo 'active open'; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-briefcase"></i>
                <div>プロジェクト</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item <?php if($directory == 'project' && $page == 'index') echo 'active'; ?>">
                  <a href="<?=$root?>project/" class="menu-link" data-pjax>
                    <div>一覧</div>
                  </a>
                </li>
                <li class="menu-item <?php if($directory == 'project' && $page == 'task') echo 'active'; ?>">
                  <a href="<?=$root?>project/task.php" class="menu-link" data-pjax>
                    <div>マイタスク</div>
                  </a>
                </li>
              </ul>
            </li>

            <li class="menu-item <?php if($directory == 'schedule') echo 'active open'; ?>">
              <a href="<?=$root?>schedule/" class="menu-link">
                <i class="menu-icon icon-base ti tabler-calendar-event"></i>
                <div>カレンダー</div>
              </a>
            </li>
            

            <?php if($_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager'){ ?>
            <li class="menu-item <?php if($directory == 'timecard') echo 'active open'; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-calendar"></i>
                <div>勤怠管理</div>
              </a>

              <ul class="menu-sub">
                <li class="menu-item <?php if($directory == 'timecard' && $page == 'index') echo 'active'; ?>">
                  <a href="<?=$root?>timecard/" class="menu-link" data-pjax>
                    <div>タイムカード</div>
                  </a>
                </li>
                <?php if($_SESSION['authority'] == 'administrator'){ ?>
                <li class="menu-item <?php if($directory == 'timecard' && $page == 'holiday') echo 'active'; ?>">
                  <a href="<?=$root?>timecard/holiday.php" class="menu-link" data-pjax>
                    <div>休日設定</div>
                  </a>
                </li>
                <?php } ?>
                <?php if($_SESSION['authority'] == 'administrator'){ ?>
                <li class="menu-item <?php if($directory == 'timecard' && ($page == 'config' || $page == 'add_config')) echo 'active'; ?>">
                  <a href="<?=$root?>timecard/config.php" class="menu-link" data-pjax>
                    <div>タイムカード設定</div>
                  </a>
                </li>
                <?php } ?>
                <?php if($_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager'){ ?>
                <li class="menu-item <?php if($directory == 'timecard' && $page == 'group') echo 'active'; ?>">
                  <a href="<?=$root?>timecard/group.php" class="menu-link" data-pjax>
                    <div>時間合計</div>
                  </a>
                </li>
                <?php } ?>
              </ul>
            </li>
            <li class="menu-item <?php if($directory == 'member' || $directory == 'administration') echo 'active open'; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-users"></i>
                <div>メンバー</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item <?php if($directory == 'member' && $page == 'index') echo 'active'; ?>">
                  <a href="<?=$root?>member/" class="menu-link" data-pjax>
                    <div>一覧</div>
                  </a>
                </li>
                <?php if($_SESSION['authority'] == 'administrator'){ ?>
                <li class="menu-item <?php if($directory == 'administration') echo 'active'; ?>">
                  <a href="<?=$root?>group/" class="menu-link" data-pjax>
                    <div>グループ設定</div>
                  </a>
                </li>
                <?php } ?>
              </ul>
            </li>
            <li class="menu-item <?php if($directory == 'addressbook' || $directory == 'folder') echo 'active open'; ?>">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-address-book"></i>
                <div>アドレス帳</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item <?php if($directory == 'addressbook' && $page == 'index') echo 'active'; ?>">
                  <a href="<?=$root?>addressbook/" class="menu-link" data-pjax>
                    <div>一覧</div>
                  </a>
                </li>
                <li class="menu-item <?php if($directory == 'addressbook' && str_contains($page, 'category')) echo 'active'; ?>">
                  <a href="<?=$root?>folder/category.php?type=addressbook" class="menu-link" data-pjax>
                    <div>カテゴリ管理</div>
                  </a>
                </li>
              </ul>
            </li>

            <?php } ?>

            <?php if($_SESSION['authority'] != 'administrator' && $_SESSION['authority'] != 'manager'){ 
              // member menu
              ?>
              <li class="menu-item <?php if($directory == 'timecard') echo 'active'; ?>">
                <a href="<?=$root?>timecard/" class="menu-link">
                  <i class="menu-icon icon-base ti tabler-calendar"></i>
                  <div data-i18n="タイムカード">タイムカード</div>
                </a>
              </li>
              <li class="menu-item <?php if($directory == 'member') echo 'active'; ?>">
                <a href="<?=$root?>member/" class="menu-link">
                  <i class="menu-icon icon-base ti tabler-users"></i>
                  <div data-i18n="メンバー">メンバー</div>
                </a>
              </li>
              <li class="menu-item <?php if($directory == 'addressbook') echo 'active open'; ?>">
                <a href="<?=$root?>addressbook/" class="menu-link">
                  <i class="menu-icon icon-base ti tabler-address-book"></i>
                  <div>アドレス帳</div>
                </a>
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
                <!-- Style Switcher -->
                <li class="nav-item dropdown me-3 me-xl-2">
                  <a
                    class="nav-link dropdown-toggle hide-arrow btn btn-icon btn-text-secondary rounded-pill"
                    id="nav-theme"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <i class="icon-base ti tabler-sun icon-22px theme-icon-active text-heading"></i>
                    <span class="d-none ms-2" id="nav-theme-text">テーマ</span>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-theme-text">
                    <li>
                      <button
                        type="button"
                        class="dropdown-item align-items-center active"
                        data-bs-theme-value="light"
                        aria-pressed="false">
                        <span><i class="icon-base ti tabler-sun icon-22px me-3" data-icon="sun"></i>ライト</span>
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
                          >ダーク</span
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
                          >システム</span
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
                        <h6 class="mb-0 me-auto">ショートカット</h6>
                        
                      </div>
                    </div>
                    <div class="dropdown-shortcuts-list scrollable-container">
                      <div class="row row-bordered overflow-visible">
                        <div class="dropdown-shortcuts-item col">
                          <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                            <i class="icon-base ti tabler-tool icon-26px text-heading"></i>
                          </span>
                          <a href="http://tools.caily.com.vn/?lang=ja" target="_blank" class="stretched-link">CAILYツール</a>
                        </div>
                        <div class="dropdown-shortcuts-item col">
                          <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                            <i class="icon-base ti tabler-server icon-26px text-heading"></i>
                          </span>
                          <a href="http://caily.ddns.net:9000/" target="_blank" class="stretched-link">CAILY NAS</a>
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
                        <h6 class="mb-0 me-auto">変更履歴</h6>
                      </div>
                    </li>
                    <li class="dropdown-notifications-list scrollable-container">
                      <ul class="list-group list-group-flush">
                        <li class="list-group-item list-group-item-action dropdown-notifications-item">
                          <div class="d-flex">
                            <div class="flex-grow-1">
                              <h6 class="small mb-1">2025年5月21日</h6>
                              <small class="mb-1 d-block text-body">
                                <ul>
                                  <li>
                                    <p>UIを変更しました。</p>
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
                      <!-- <span class="badge rounded-pill bg-danger badge-dot badge-notifications border"></span> -->
                    </span>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end p-0">
                    <li class="dropdown-menu-header border-bottom">
                      <div class="dropdown-header d-flex align-items-center py-3">
                        <h6 class="mb-0 me-auto">通知</h6>
                        <div class="d-flex align-items-center h6 mb-0">
                          <!-- <span class="badge bg-label-primary me-2">8 New</span> -->
                          <a
                            href="javascript:void(0)"
                            class="dropdown-notifications-all p-2 btn btn-icon"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Mark all as read"
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
                              <h6 class="small mb-1">通知はありません 🎉</h6>
                              <small class="mb-1 d-block text-body">作成中...</small>
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
                    <li class="border-top">
                      <div class="d-grid p-4">
                        <a class="btn btn-primary btn-sm d-flex" href="javascript:void(0);">
                          <small class="align-middle">すべての通知を表示</small>
                        </a>
                      </div>
                    </li>
                  </ul>
                </li>
                <!--/ Notification -->

                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a
                    class="nav-link dropdown-toggle hide-arrow p-0"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
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
                            <div class="avatar avatar-online">
                              <?php if($_SESSION['user_image'] != '') {
                                echo '<img src="'.$root.'assets/upload/avatar/'.$_SESSION['user_image'].'" alt class="rounded-circle" />';
                              } else{
                                echo '<img src="'.$root.'assets/img/avatars/1.png" alt class="rounded-circle" />';
                              }
                              ?>
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            <h6 class="mb-0"><?=$realname?></h6>
                            <small class="text-body-secondary"><?=$groupname?></small>
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
                        ><span class="align-middle">個人情報</span>
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
                          <small class="align-middle">ログアウト</small>
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

            