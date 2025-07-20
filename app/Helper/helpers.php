<?php

// for side bar menu active
function set_active($route) {
    if (is_array($route )) {
        return in_array(Request::path(), $route) ? 'active' : '';
    }
    return Request::path() == $route ? 'active' : '';
}

// for side bar menu show
function set_show($route) {
    if (is_array($route )) {
        return in_array(Request::path(), $route) ? 'show' : '';
    }
    return Request::path() == $route ? 'show' : '';
}
