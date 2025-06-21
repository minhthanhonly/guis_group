<div class="app-chat card overflow-hidden">
    <div class="row g-0">
      <!-- Sidebar Left -->
      <div class="col app-chat-sidebar-left app-sidebar overflow-hidden" id="app-chat-sidebar-left">
        <div class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-6 pt-12">
          <div class="avatar avatar-xl avatar-online chat-sidebar-avatar" id="user_avatar">
            <?php if($_SESSION['user_image'] != '') {
                echo '<img src="'.ROOT.'assets/upload/avatar/'.$_SESSION['user_image'].'" alt class="rounded-circle" />';
            } else{
                echo '<img src="'.ROOT.'assets/img/avatars/1.png" alt class="rounded-circle" />';
            }?>
          </div>
          <h5 class="mt-4 mb-0"><?=$realname?></h5>
          <span><?=$groupname?></span>
          <i class="icon-base ti tabler-x icon-lg cursor-pointer close-sidebar" data-bs-toggle="sidebar" data-overlay data-target="#app-chat-sidebar-left"></i>
        </div>
        <div class="sidebar-body px-6 pb-6">
          
        </div>
      </div>
      <!-- /Sidebar Left-->

      <!-- Chat & Contacts -->
      <div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
        <div class="sidebar-header h-px-75 px-5 border-bottom d-flex align-items-center">
          <div class="d-flex align-items-center me-6 me-lg-0">
            <div class="flex-shrink-0 avatar avatar-online me-4" data-userid="<?=$_SESSION['userid']?>" data-bs-toggle="sidebar" data-overlay="app-overlay-ex" data-target="#app-chat-sidebar-left">
              <?php if($_SESSION['user_image'] != '') {
                echo '<img src="'.ROOT.'assets/upload/avatar/'.$_SESSION['user_image'].'" alt class="user-avatar rounded-circle cursor-pointer" />';
              } else{
                  echo '<img src="'.ROOT.'assets/img/avatars/1.png" alt class="user-avatar rounded-circle cursor-pointer" />';
              }?>
            </div>
            <div class="flex-grow-1 input-group input-group-merge">
              <span class="input-group-text" id="basic-addon-search31"><i class="icon-base ti tabler-search icon-xs"></i></span>
              <input type="text" class="form-control chat-search-input" placeholder="検索..." aria-label="検索..." aria-describedby="basic-addon-search31" />
            </div>
          </div>
          <i class="icon-base ti tabler-x icon-lg cursor-pointer position-absolute top-50 end-0 translate-middle d-lg-none d-block" data-overlay data-bs-toggle="sidebar" data-target="#app-chat-contacts"></i>
        </div>
        <div class="sidebar-body">
          <!-- Chats -->
          <ul class="list-unstyled chat-contact-list py-2 mb-0" id="chat-list">
            <li class="chat-contact-list-item chat-contact-list-item-title mt-0">
              <h5 class="text-primary mb-0">チャット</h5>
            </li>
            <li class="chat-contact-list-item chat-list-item-0 d-none">
              <h6 class="text-body-secondary mb-0">チャットが見つかりません</h6>
            </li>
            <li class="chat-contact-list-item mb-1">
              <a class="d-flex align-items-center">
                <div class="flex-shrink-0 avatar avatar-online">
                  <img src="<?=ROOT?>assets/img/avatars/ai.png" alt="Avatar" class="rounded-circle" />
                </div>
                <div class="chat-contact-info flex-grow-1 ms-4">
                  <div class="d-flex justify-content-between align-items-center">
                    <h6 class="chat-contact-name text-truncate m-0 fw-normal">AIちゃん</h6>
                    <!-- <small class="chat-contact-list-item-time">5 Minutes</small> -->
                  </div>
                </div>
              </a>
            </li>
          </ul>
          <!-- Contacts -->
          <ul class="list-unstyled chat-contact-list mb-0 py-2" id="contact-list">
            <li class="chat-contact-list-item chat-contact-list-item-title mt-0">
              <h5 class="text-primary mb-0">連絡先</h5>
            </li>
            <li class="chat-contact-list-item contact-list-item-0 d-none">
              <h6 class="text-body-secondary mb-0">連絡先が見つかりません</h6>
            </li>

            <?php foreach($user_list as $user){ ?>
            <li class="chat-contact-list-item">
              <a class="d-flex align-items-center">
                <div class="flex-shrink-0 avatar" data-userid="<?=$user['userid']?>">
                  <?php if($user['user_image'] != '') {
                      echo '<img src="'.ROOT.'assets/upload/avatar/'.$user['user_image'].'" alt="Avatar" class="rounded-circle" />';
                  } else{
                      echo '<img src="'.ROOT.'assets/img/avatars/1.png" alt="Avatar" class="rounded-circle" />';
                  }?>
                </div>
                <div class="chat-contact-info flex-grow-1 ms-4">
                  <h6 class="chat-contact-name text-truncate m-0 fw-normal"><?=$user['realname']?></h6>
                  <small class="chat-contact-status text-truncate"><?=$user['user_groupname']?></small>
                </div>
              </a>
            </li>
            <?php } ?>
           
          </ul>
        </div>
      </div>
      <!-- /Chat contacts -->

      <!-- Chat conversation -->
      <div class="col app-chat-conversation d-flex align-items-center justify-content-center flex-column d-none" id="app-chat-conversation">
        <div class="bg-label-primary p-8 rounded-circle">
          <i class="icon-base ti tabler-message-2 icon-50px"></i>
        </div>
        <p class="my-4">連絡先を選択してチャットを開始してください。</p>
        <button class="btn btn-primary app-chat-conversation-btn" id="app-chat-conversation-btn">連絡先を選択</button>
      </div>
      <!-- /Chat conversation -->

      <!-- Chat History -->
      <div class="col app-chat-history " id="app-chat-history">
        <div class="chat-history-wrapper">
          <div class="chat-history-header border-bottom">
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex overflow-hidden align-items-center">
                <i class="icon-base ti tabler-menu-2 icon-lg cursor-pointer d-lg-none d-block me-4" data-bs-toggle="sidebar" data-overlay data-target="#app-chat-contacts"></i>
                <div class="flex-shrink-0 avatar avatar-online">
                  <img src="<?=ROOT?>assets/img/avatars/ai.png" alt="Avatar" class="rounded-circle" data-bs-toggle="sidebar" data-overlay data-target="#app-chat-sidebar-right" />
                </div>
                <div class="chat-contact-info flex-grow-1 ms-4">
                  <h6 class="m-0 fw-normal">AIちゃん</h6>
                  <small class="user-status text-body">AIです</small>
                </div>
              </div>
              <div class="d-flex align-items-center">
                <!-- <span class="btn btn-text-secondary cursor-pointer d-sm-inline-flex d-none me-1 btn-icon rounded-pill">
                  <i class="icon-base ti tabler-phone icon-22px"></i>
                </span>
                <span class="btn btn-text-secondary cursor-pointer d-sm-inline-flex d-none me-1 btn-icon rounded-pill">
                  <i class="icon-base ti tabler-video icon-22px"></i>
                </span>
                <span class="btn btn-text-secondary cursor-pointer d-sm-inline-flex d-none me-1 btn-icon rounded-pill">
                  <i class="icon-base ti tabler-search icon-22px"></i>
                </span>
                <div class="dropdown">
                  <button class="btn btn-icon btn-text-secondary text-secondary rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="true" id="chat-header-actions"><i class="icon-base ti tabler-dots-vertical icon-22px"></i></button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="chat-header-actions">
                    <a class="dropdown-item" href="javascript:void(0);">View Contact</a>
                    <a class="dropdown-item" href="javascript:void(0);">Mute Notifications</a>
                    <a class="dropdown-item" href="javascript:void(0);">Block Contact</a>
                    <a class="dropdown-item" href="javascript:void(0);">Clear Chat</a>
                    <a class="dropdown-item" href="javascript:void(0);">Report</a>
                  </div>
                </div> -->
              </div>
            </div>
          </div>
          <div class="chat-history-body">
            <ul class="list-unstyled chat-history" id="chat-history">
              <li class="chat-message">
                <div class="d-flex overflow-hidden">
                  <div class="user-avatar flex-shrink-0 me-4">
                    <div class="avatar avatar-sm">
                      <img src="<?=ROOT?>assets/img/avatars/ai.png" alt="Avatar" class="rounded-circle" />
                    </div>
                  </div>
                  <div class="chat-message-wrapper flex-grow-1">
                    <div class="chat-message-text">
                      <p class="mb-0">私はGUIS社のアシスタントです。</p>
                    </div>
                    <div class="chat-message-text mt-2">
                      <p class="mb-0">何かお手伝いできることはありますか？</p>
                    </div>
                    <div class="text-body-secondary mt-1">
                      <small><?php echo date('H:i:s'); ?></small>
                    </div>
                  </div>
                </div>
              </li>
              
            </ul>
          </div>
          <!-- Chat message form -->
          <div class="chat-history-footer shadow-xs">
            <form class="form-send-message d-flex justify-content-between align-items-center ">
              <input class="form-control message-input border-0 me-4 shadow-none" placeholder="メッセージを入力してください..." autofocus />
              <div class="message-actions d-flex align-items-center">
                <!-- <span class="btn btn-text-secondary btn-icon rounded-pill cursor-pointer">
                  <i class="speech-to-text icon-base ti tabler-microphone icon-22px text-heading"></i>
                </span> -->
                <!-- <label for="attach-doc" class="form-label mb-0">
                  <span class="btn btn-text-secondary btn-icon rounded-pill cursor-pointer mx-1">
                    <i class="icon-base ti tabler-paperclip icon-22px text-heading"></i>
                  </span>
                  <input type="file" id="attach-doc" hidden />
                </label> -->
                <button class="btn btn-primary d-flex send-msg-btn">
                  <i class="icon-base ti tabler-send icon-16px flex-shrink-0"></i>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- /Chat History -->

      <!-- Sidebar Right -->
      <!-- <div class="col app-chat-sidebar-right app-sidebar overflow-hidden" id="app-chat-sidebar-right">
        <div class="sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-6 pt-12">
          <div class="avatar avatar-xl avatar-online chat-sidebar-avatar">
            <img src="<?=ROOT?>assets/img/avatars/ai.png" alt="Avatar" class="rounded-circle" />
          </div>
          <h5 class="mt-4 mb-0">Felecia Rower</h5>
          <span>NextJS Developer</span>
          <i class="icon-base ti tabler-x icon-lg cursor-pointer close-sidebar d-block" data-bs-toggle="sidebar" data-overlay data-target="#app-chat-sidebar-right"></i>
        </div>
        <div class="sidebar-body p-6 pt-0">
          <div class="my-6">
            <p class="text-uppercase mb-1 text-body-secondary">About</p>
            <p class="mb-0">It is a long established fact that a reader will be distracted by the readable content .</p>
          </div>
          <div class="my-6">
            <p class="text-uppercase mb-1 text-body-secondary">Personal Information</p>
            <ul class="list-unstyled d-grid gap-4 mb-0 ms-2 py-2 text-heading">
              <li class="d-flex align-items-center">
                <i class="icon-base ti tabler-mail icon-md"></i>
                <span class="align-middle ms-2">josephGreen@email.com</span>
              </li>
              <li class="d-flex align-items-center">
                <i class="icon-base ti tabler-phone-call icon-md"></i>
                <span class="align-middle ms-2">+1(123) 456 - 7890</span>
              </li>
              <li class="d-flex align-items-center">
                <i class="icon-base ti tabler-clock icon-md"></i>
                <span class="align-middle ms-2">Mon - Fri 10AM - 8PM</span>
              </li>
            </ul>
          </div>
          <div class="my-6">
            <p class="text-uppercase text-body-secondary mb-1">Options</p>
            <ul class="list-unstyled d-grid gap-4 ms-2 py-2 text-heading">
              <li class="cursor-pointer d-flex align-items-center">
                <i class="icon-base ti tabler-bookmark icon-md"></i>
                <span class="align-middle ms-2">Add Tag</span>
              </li>
              <li class="cursor-pointer d-flex align-items-center">
                <i class="icon-base ti tabler-star icon-md"></i>
                <span class="align-middle ms-2">Important Contact</span>
              </li>
              <li class="cursor-pointer d-flex align-items-center">
                <i class="icon-base ti tabler-photo icon-md"></i>
                <span class="align-middle ms-2">Shared Media</span>
              </li>
              <li class="cursor-pointer d-flex align-items-center">
                <i class="icon-base ti tabler-ban icon-md"></i>
                <span class="align-middle ms-2">Block Contact</span>
              </li>
            </ul>
          </div>
          <div class="d-flex mt-6">
            <button class="btn btn-danger w-100" data-bs-toggle="sidebar" data-overlay data-target="#app-chat-sidebar-right">Delete Contact<i class="icon-base ti tabler-trash icon-16px ms-2"></i></button>
          </div>
        </div>
      </div> -->
      <!-- /Sidebar Right -->

      <div class="app-overlay"></div>
    </div>
  </div>