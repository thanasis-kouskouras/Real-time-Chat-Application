<?php
require_once 'config.php';
$path = $GLOBALS['rootUrl'] . '/static';
require_once "myfavicon.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Reset Link Sent</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <script src='<?php echo $path . "/js/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_js_bootstrap.bundle.js" ?>'>
    </script>
    <link rel="stylesheet"
        href='<?php echo $path . "/css/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_css_bootstrap.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/fontawesome-all.6.4.0.css" ?>' />

    <link rel="stylesheet" href='<?php echo $path . "/css/header.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/forgot-password.css" ?>' />
</head>

<body class="d-flex vw-100 responsive-height align-items-center justify-content-center">

    <div class="container mt-5 p-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card p-4">
                    <div class="text-center">
                        <div class="mt-4">
                            <div class="logout-checkmark">
                                <svg id="svg-forgot-email" xmlns:x="&amp;ns_extend;" xmlns:i="&amp;ns_ai;"
                                    xmlns:graph="&amp;ns_graphs;" xmlns="http://www.w3.org/2000/svg"
                                    xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 98 98"
                                    xml:space="preserve">
                                    <g i:extraneous="self">
                                        <circle id="XMLID_50_" class="st0" cx="49" cy="49" r="49"></circle>
                                        <g id="XMLID_4_">
                                            <path id="XMLID_49_" class="st1" d="M77.3,42.7V77c0,0.6-0.4,1-1,1H21.7c-0.5,0-1-0.5-1-1V42.7c0-0.3,0.1-0.6,0.4-0.8l27.3-21.7
                                                c0.3-0.3,0.8-0.3,1.2,0l27.3,21.7C77.1,42.1,77.3,42.4,77.3,42.7z">
                                            </path>
                                            <path id="XMLID_48_" class="st2" d="M66.5,69.5h-35c-1.1,0-2-0.9-2-2V26.8c0-1.1,0.9-2,2-2h35c1.1,0,2,0.9,2,2v40.7
                                                C68.5,68.6,67.6,69.5,66.5,69.5z"></path>
                                            <path id="XMLID_47_" class="st1" d="M62.9,33.4H47.2c-0.5,0-0.9-0.4-0.9-0.9v-0.2c0-0.5,0.4-0.9,0.9-0.9h15.7
                                                c0.5,0,0.9,0.4,0.9,0.9v0.2C63.8,33,63.4,33.4,62.9,33.4z"></path>
                                            <path id="XMLID_46_" class="st1" d="M62.9,40.3H47.2c-0.5,0-0.9-0.4-0.9-0.9v-0.2c0-0.5,0.4-0.9,0.9-0.9h15.7
                                                c0.5,0,0.9,0.4,0.9,0.9v0.2C63.8,39.9,63.4,40.3,62.9,40.3z"></path>
                                            <path id="XMLID_45_" class="st1" d="M62.9,47.2H47.2c-0.5,0-0.9-0.4-0.9-0.9v-0.2c0-0.5,0.4-0.9,0.9-0.9h15.7
                                                c0.5,0,0.9,0.4,0.9,0.9v0.2C63.8,46.8,63.4,47.2,62.9,47.2z"></path>
                                            <path id="XMLID_44_" class="st1" d="M62.9,54.1H47.2c-0.5,0-0.9-0.4-0.9-0.9v-0.2c0-0.5,0.4-0.9,0.9-0.9h15.7
                                                c0.5,0,0.9,0.4,0.9,0.9v0.2C63.8,53.7,63.4,54.1,62.9,54.1z"></path>
                                            <path id="XMLID_43_" class="st2" d="M41.6,40.1h-5.8c-0.6,0-1-0.4-1-1v-6.7c0-0.6,0.4-1,1-1h5.8c0.6,0,1,0.4,1,1v6.7
                                                C42.6,39.7,42.2,40.1,41.6,40.1z"></path>
                                            <path id="XMLID_42_" class="st2" d="M41.6,54.2h-5.8c-0.6,0-1-0.4-1-1v-6.7c0-0.6,0.4-1,1-1h5.8c0.6,0,1,0.4,1,1v6.7
                                                C42.6,53.8,42.2,54.2,41.6,54.2z"></path>
                                            <path id="XMLID_41_" class="st1"
                                                d="M23.4,46.2l25,17.8c0.3,0.2,0.7,0.2,1.1,0l26.8-19.8l-3.3,30.9H27.7L23.4,46.2z">
                                            </path>
                                            <path id="XMLID_40_" class="st3"
                                                d="M74.9,45.2L49.5,63.5c-0.3,0.2-0.7,0.2-1.1,0L23.2,45.2"></path>
                                        </g>
                                    </g>
                                </svg>
                            </div>
                        </div>
                        <p class="text-muted mt-2">A <b>reset password link</b> has been <b>send to</b> your <b>Email
                                address</b>. Please check for an email from <b>EasyTalk</b> and click on the included
                            link to
                            reset your password. </p>
                        <a href="login.php"
                            class="btn btn-primary btn-md btn-block btn-gradient waves-effect waves-light mt-3">Back to
                            Login</a>
                        <?php require 'footerMessage.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require 'footer.php'; ?>