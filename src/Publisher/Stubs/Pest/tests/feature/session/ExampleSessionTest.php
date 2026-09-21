<?php

test("simple session", function () {
    $session = service('session');

    $session->set('logged_in', 123);
    $this->assertSame(123, $session->get('logged_in'));
});
