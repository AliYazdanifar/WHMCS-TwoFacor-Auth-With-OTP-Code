<?php

use WHMCS\Database\Capsule;

function otp_config()
{
    return array(
        "FriendlyName" =>
            array(
                "Type" => "System",
                "Value" => "otp"),
        "ShortDescription" =>
            array(
                "Type" => "System",
                "Value" => "two steps auth with otp."),
        "Description" =>
            array(
                "Type" => "System",
                "Value" => "after enter email and password user recieve otp code"),
//        "sms_username" =>
//            array(
//                "FriendlyName" => "sms API username",
//                "Type" => "text",
//                "Description" => "api username for connect to sms panel"),
//        "sms_password" =>
//            array(
//                "FriendlyName" => "sms API password",
//                "Type" => "text",
//                "Description" => "api pass.word for connect to sms panel"),
    );
}

function otp_activate($params)
{
    $html = '
    <label for="type">روش دریافت کد فعالسازی</label>
    <select class="form-control" name="receive_type">
    <option value="email">دریافت کد با Email</option>
    <option value="sms">دریافت کد با SMS</option>
</select>
<hr>
    <input type="submit" value="فعال سازی" class="btn btn-primary">
    ';
    return $html;
}

function otp_activateverify($params)
{
    $type = $params["post_vars"]["receive_type"];
    insertIntoOtpTbl($_SESSION['uid'], $type);
    return array("msg" => "با موفقیت فعال شد!", "settings" => array("otpprefix" => sha1($otp)));
}

function otp_challenge($params)
{
    $user = findUser($params['user_info']['id']);

    $oldDate = \Carbon\Carbon::make($user['updated_at']);
    $diff = \Carbon\Carbon::now()->diffInSeconds($oldDate);

    if ($diff > 120) {
        $code = rand(10000, 99999);
        $user['receive_type'] == 'sms' ? sendSms($code) : sendEmail($code);
        saveOtpCodeForUser($params['user_info']['id'], $code);
    }
    $output = "
    <form action='dologin.php' method='post'>
        <div align='center'>
            <h4>لطفا کد ارسال شده را در کادر زیر وارد کنید.</h4>
            <input type='password' name='otp' min='5' required class='form-control text-center' placeholder='کد ارسال شده را وارد کنید' autofocus>
        </div>
        <br>
        <button class='btn btn-primary'>بررسی</button>
    </form>
    <hr>
    <button id='btn-timer' type='submit' onclick='location.reload()'>
    <div>ارسال مجدد کد بعد از <span id=\"timer\"></span></div>
    </button>
    <script>
    let timerOn = true;

function timer(remaining) {
  var m = Math.floor(remaining / 60);
  var s = remaining % 60;
  
  m = m < 10 ? '0' + m : m;
  s = s < 10 ? '0' + s : s;
  document.getElementById('timer').innerHTML = m + ':' + s;
  document.getElementById('btn-timer').disabled = true;
  remaining -= 1;
  
  if(remaining >= 0 && timerOn) {
    setTimeout(function() {
        timer(remaining);
    }, 1000);
    return;
  }

  if(!timerOn) {
    // Do validate stuff here
    return;
  }
  
  // Do timeout stuff here
  document.getElementById('btn-timer').disabled = false;
  document.getElementById('btn-timer').innerHTML = 'اگر کد دریافت نکردید اینجا کلیک کنید';
}

timer(130);
</script>
    ";
    logModuleCall("otp", "challenge", "", "");
    return $output;
}

function otp_verify($params)
{
    return isCorrectOtp($params['post_vars']['otp'], $params['user_info']['id']);

}

function isCorrectOtp($otp, $userId)
{
    $user = findUser($userId);
    return $otp == $user['otp'];
}

function dd($data)
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    die();
}

function sendEmail($code)
{
    return true;
}

function sendSms($code)
{
    return true;
}

function findUser($userId)
{
    $pdo = Capsule::connection()->getPdo();
    $query = "SELECT * FROM `tblotpsecuritymodule` WHERE user_id=:uid";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':uid' => trim($userId)]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function insertIntoOtpTbl($userId, $receiveType)
{
    $pdo = Capsule::connection()->getPdo();
    $query = "SELECT `auth_user_id` FROM `tblusers_clients` WHERE `client_id`=:cid";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':cid' => trim($userId)]);
    $id = $stmt->fetch(PDO::FETCH_ASSOC)['auth_user_id'];
    $res = findUser($id);

    if (!empty($res) || $res != '' || $res != [])
        return updateReceiveType($id, $receiveType);

    $query = "INSERT INTO `tblotpsecuritymodule`(`user_id`, `receive_type`) VALUES (:uid,:rt)";
    $stmt = $pdo->prepare($query);

    $stmt->execute([':uid' => trim($id), ':rt' => trim($receiveType)]);
    return $pdo->lastInsertId();
}

function saveOtpCodeForUser($userId, $otp)
{

    $pdo = Capsule::connection()->getPdo();
    $query = "UPDATE `tblotpsecuritymodule` set `otp` = :otp WHERE user_id=:uid";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([':uid' => trim($userId), ':otp' => trim($otp)]);
}

function updateReceiveType($userId, $type)
{
    $pdo = Capsule::connection()->getPdo();
    $query = "UPDATE `tblotpsecuritymodule` set `receive_type` = :rt WHERE user_id=:uid";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([':rt' => trim($type), ':uid' => trim($userId)]);
}
