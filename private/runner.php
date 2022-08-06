<?php

require_once dirname(__FILE__, 2) . '/bootstrap.php';

return function (array $context) {
    return new \App\JobKernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};