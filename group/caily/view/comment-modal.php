<div class="modal fade" id="modalComment" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered modal-chat-w">
    <div class="modal-content p-0">
      <div class="modal-body1">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        <div id="comment-app">
          <div class="app-chat card overflow-hidden">
            <div class="row g-0">
              <!-- Sidebar Left: Members/Managers -->
              <div class="col app-chat-contacts app-sidebar flex-grow-0 overflow-hidden border-end" id="app-chat-contacts">
                <div class="chat-sidebar-left-user sidebar-header d-flex flex-column justify-content-center align-items-center flex-wrap px-6 pt-4 pb-2">
                  <div class="avatar avatar-xl avatar-online chat-sidebar-avatar mb-2">
                    <img src="/assets/img/avatars/ai.png" alt class="rounded-circle" />
                  </div>
                  <h5 class="mt-2 mb-0">プロジェクト</h5>
                  <span>メンバー一覧</span>
                </div>
                <div class="sidebar-body px-3 pb-3">
                  <ul class="list-unstyled chat-contact-list mb-0 py-2">
                    <li class="chat-contact-list-item chat-contact-list-item-title mt-0">
                      <h6 class="text-primary mb-0">マネージャー</h6>
                    </li>
                    <li v-for="manager in managers" :key="'manager-' + manager.user_id" class="chat-contact-list-item">
                      <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 avatar avatar-online">
                          <img v-if="manager.user_image" :src="getAvatarSrc(manager)" alt="Avatar" class="rounded-circle" />
                          <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(manager.user_name) }}</span>
                        </div>
                        <div class="chat-contact-info flex-grow-1 ms-3">
                          <div class="chat-contact-name text-truncate m-0 fw-normal">{{ manager.user_name }}</div>
                        </div>
                      </div>
                    </li>
                    <li class="chat-contact-list-item chat-contact-list-item-title mt-2">
                      <h6 class="text-primary mb-0">メンバー</h6>
                    </li>
                    <li v-for="member in members" :key="'member-' + member.user_id" class="chat-contact-list-item">
                      <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 avatar">
                          <img v-if="member.user_image" :src="getAvatarSrc(member)" alt="Avatar" class="rounded-circle" />
                          <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(member.user_name) }}</span>
                        </div>
                        <div class="chat-contact-info flex-grow-1 ms-3">
                          <div class="chat-contact-name text-truncate m-0 fw-normal">{{ member.user_name }}</div>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
              <!-- /Sidebar Left -->
              <div class="col app-chat-history">
                <div class="chat-history-wrapper">
                  <div class="chat-history-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="d-flex overflow-hidden align-items-center">
                        <div class="flex-shrink-0 avatar avatar-online">
                          <img src="/assets/img/avatars/ai.png" alt="Avatar" class="rounded-circle" />
                        </div>
                        <div class="chat-contact-info flex-grow-1 ms-4">
                          <h6 class="m-0 fw-normal">コメント</h6>
                          <small class="user-status text-body">プロジェクトコメント</small>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="chat-history-body" id="chat-history-project-ps" style="overflow-y: auto;">
                    <ul class="list-unstyled chat-history" id="chat-history-project" 
                        @scroll="handleScroll">
                      <li v-for="comment in displayedComments" :key="comment.id" 
                          class="chat-message" 
                          :class="{'chat-message-right': String(comment.user_id) === String(USER_ID)}">
                        <div 
                          class="d-flex overflow-hidden gap-4"
                          :class="{'flex-row-reverse': String(comment.user_id) === String(USER_ID)}">
                          <div class="user-avatar flex-shrink-0">
                            <div class="avatar avatar-sm">
                              <img v-if="!comment.avatarError" class="rounded-circle" :src="getAvatarSrc(comment)" :alt="comment.user_name" @error="handleAvatarError(comment)">
                              <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(comment.user_name) }}</span>
                            </div>
                          </div>
                          <div class="chat-message-wrapper flex-grow-1">
                            <div class="chat-message-text">
                              <div class="mb-0" v-html="renderMentions(comment.content)"></div>
                            </div>
                            <div class="text-body-secondary mt-1">
                              <small>{{ formatDateTime(comment.created_at) }}</small>
                            </div>
                          </div>
                        </div>
                      </li>
                      <li v-if="comments.length === 0" class="chat-message text-center text-muted py-5">
                        コメントはまだありません
                      </li>
                    </ul>
                  </div>
                  <div class="chat-history-footer shadow-xs">
                    <div class="form-send-message d-flex justify-content-between align-items-center ">
                        <div class="form-control message-input border-0 me-4 shadow-none allow-mention"
                             contenteditable="true"
                             data-mention
                             data-html-mention
                             @input="onCommentInput"
                             placeholder="コメントを入力... @でメンション"
                             style="min-height: 38px; max-height: 120px; overflow-y: auto; white-space: pre-wrap;"></div>
                        <button class="btn btn-primary d-flex send-msg-btn"
                                @click="addComment"
                                :disabled="!hasCommentContent()">
                          <i class="fa fa-paper-plane icon-16px flex-shrink-0"></i>
                        </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<button data-bs-toggle="modal" data-bs-target="#modalComment" id="ai-chat-toggle" class="btn btn-primary rounded-circle position-fixed waves-effect waves-light me-12"><i class="icon-base ti tabler-message-circle-2 icon-md"></i></button>
<link rel="stylesheet" href="<?=ROOT?>assets/css/mention.css" />
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
<script src="/assets/js/mention.js"></script>
<script src="/assets/js/comment.js"></script>
<script>
(function() {
  let commentAppMounted = false;
  document.addEventListener('shown.bs.modal', function(e) {
    if (e.target && e.target.id === 'modalComment' && !commentAppMounted) {
      if (window.Vue && window.Vue.createApp && window.projectDetailVueOptions) {
        window.commentApp = window.Vue.createApp(window.projectDetailVueOptions);
        window.commentApp.mount('#comment-app');
        commentAppMounted = true;
      }
    }
  });
  
    // let psWrapper = document.getElementById('chat-history-project-ps');
    // let ps = new PerfectScrollbar(psWrapper);
})();

</script> 