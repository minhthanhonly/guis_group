<?php
/**
 * After login the user returns to the page they asked for, never to a background request.
 * Bug: a notification poll made while logged out overwrote the remembered page with
 * /api/NotificationAPI.php?..., and login then redirected there.
 */
require dirname(__DIR__) . '/ai/bootstrap.php';

$nav = function (array $server) {
    return Authority::isPageNavigation($server + ['REQUEST_METHOD' => 'GET']);
};
check('a page opened in the browser is remembered', $nav(['REQUEST_URI' => '/project/detail.php?id=7432', 'HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_DEST' => 'document']) === true);
check('a page is remembered when the browser sends no fetch metadata', $nav(['REQUEST_URI' => '/project/detail.php?id=7432']) === true);
check('the notification poll is not remembered', $nav(['REQUEST_URI' => '/api/NotificationAPI.php?method=get_notifications&user_id=admin&limit=1', 'HTTP_SEC_FETCH_MODE' => 'cors', 'HTTP_SEC_FETCH_DEST' => 'empty']) === false);
check('an api url is not remembered even without fetch metadata', $nav(['REQUEST_URI' => '/api/NotificationAPI.php?method=get_notifications']) === false);
check('the generic api is not remembered', $nav(['REQUEST_URI' => '/api/index.php?model=project&method=list']) === false);
check('the assistant endpoint is not remembered', $nav(['REQUEST_URI' => '/ai/index.php?method=chat']) === false);
check('a background fetch to a page url is not remembered', $nav(['REQUEST_URI' => '/project/task.php?project_id=3', 'HTTP_SEC_FETCH_MODE' => 'same-origin', 'HTTP_SEC_FETCH_DEST' => 'empty']) === false);
check('an XMLHttpRequest is not remembered', $nav(['REQUEST_URI' => '/todo/index.php', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']) === false);
check('an image or script request is not remembered', $nav(['REQUEST_URI' => '/storage/view.php?id=3', 'HTTP_SEC_FETCH_MODE' => 'no-cors', 'HTTP_SEC_FETCH_DEST' => 'image']) === false);
check('a page whose name merely contains api is remembered', $nav(['REQUEST_URI' => '/apiary/index.php']) === true);

check('a remembered page is used after login', Authority::safeReferer('/project/detail.php?id=7432') === '/project/detail.php?id=7432');
check('an api url already stored in the session is ignored', Authority::safeReferer('/api/NotificationAPI.php?method=get_notifications&user_id=admin&limit=1') === null);
check('an assistant url already stored is ignored', Authority::safeReferer('/ai/index.php?method=chat') === null);
check('a protocol-relative url is ignored', Authority::safeReferer('//evil.example/x') === null);
check('an absolute url is ignored', Authority::safeReferer('https://evil.example/') === null);
check('a backslash url is ignored', Authority::safeReferer('/\evil.example') === null);
check('a url with a line break is ignored', Authority::safeReferer("/project/\r\nSet-Cookie: x=1") === null);
check('non-strings are ignored', Authority::safeReferer(null) === null && Authority::safeReferer(['x']) === null && Authority::safeReferer('') === null);
check('the login page itself is ignored', Authority::safeReferer('/login.php') === null);
finish();
