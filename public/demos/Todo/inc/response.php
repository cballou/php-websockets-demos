<?php if (!defined('SAFETY_NET')) die('Where is your safety net?');

/**
 * Simple JSON response function.
 */
function response($data, $error = false) {
    header('Content-Type: application/json');

    $status = 'success';
    $msg = FALSE;

    if ($data === FALSE) {
        $status = 'error';
        if ($error && !empty($data)) {
            $msg = $error;
        }
    } else if (isset($data['status']) && $data['status'] != 'success') {
        $status = 'error';
        if (!empty($data['msg'])) {
            $msg = $data['msg'];
        }
    }

    die(json_encode(
        array(
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        )
    ));
}
