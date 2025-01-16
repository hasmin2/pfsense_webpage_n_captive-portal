<!DOCTYPE HTML>
<html lang="ko">
<head>
    <?php
    include_once("auth.inc");
    include_once("common_ui.inc");
    include_once("terminal_status.inc");
    echo print_css_n_head();
    ?>
</head>
<body>
<div id="wrapper">
    <?php echo print_sidebar( basename($_SERVER['PHP_SELF']));?>
    <div id="content">
        <div class="headline-wrap">
            <div class="title-area">
                <p class="headline">Crew Connection</p>
            </div>
        </div>

        <div class="contents">
            <div class="container">
                <div class="crew-wrap">
                    <div class="list-wrap v1">
                        <div class="sort-area">
                            <div class="inner">
                                <select name="" id="" class="select v1">
                                    <option value="">IP address</option>
                                    <option value="">MAC address</option>
                                    <option value="">User name</option>
                                    <option value="">Session start</option>
                                    <option value="">Last activity</option>
                                </select>
                                <button class="btn-ic btn-sort"></button>
                            </div>
                        </div>
                        <table>
                            <colgroup>
                                <col style="width: 15.385%;">
                                <col style="width: 15.385%;">
                                <col style="width: auto;">
                                <col style="width: 12.5%;">
                                <col style="width: 12.5%;">
                                <col style="width: 6.731%;">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>IP address<button class="btn-ic btn-sort"></button></th>
                                <th>MAC address<button class="btn-ic btn-sort"></button></th>
                                <th>User name<button class="btn-ic btn-sort"></button></th>
                                <th>Session start<button class="btn-ic btn-sort"></button></th>
                                <th>Last activity<button class="btn-ic btn-sort"></button></th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td data-th="IP address" data-th-width="100" data-width="100">192.168.208.140</td>
                                <td data-th="MAC address" data-th-width="100" data-width="100">96:17:06:b9:dc:aa</td>
                                <td data-th="User name" data-th-width="100" data-width="100">user00001</td>
                                <td data-th="Session start" data-th-width="100" data-width="100">
                                    2024.11.01 <br class="hide-mo">
                                    12:52:30
                                </td>
                                <td data-th="Last activity" data-th-width="100" data-width="100">
                                    2024.11.01 <br class="hide-mo">
                                    12:52:30
                                </td>
                                <td data-th="" data-th-width="0" data-width="100">
                                    <button class="btn-ic btn-delete red"></button>
                                </td>
                            </tr>
                            <tr>
                                <td data-th="IP address" data-th-width="100" data-width="100">192.168.208.140</td>
                                <td data-th="MAC address" data-th-width="100" data-width="100">96:17:06:b9:dc:aa</td>
                                <td data-th="User name" data-th-width="100" data-width="100">user00001</td>
                                <td data-th="Session start" data-th-width="100" data-width="100">
                                    2024.11.01 <br class="hide-mo">
                                    12:52:30
                                </td>
                                <td data-th="Last activity" data-th-width="100" data-width="100">
                                    2024.11.01 <br class="hide-mo">
                                    12:52:30
                                </td>
                                <td data-th="" data-th-width="0" data-width="100">
                                    <button class="btn-ic btn-delete red"></button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="popup layer pop-set-terminal">
    <div class="pop-head">
        <p class="title">Terminal Setting</p>
    </div>
    <div class="pop-cont">
        <p class="tit v1">Manual Override</p>
        <div class="override-list scroll-y">
            <ul>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="" checked>
                        <label for="">
                            <p class="txt-mint">Automatic</p>
                        </label>
                    </div>
                </li>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="">
                        <label for="">
                            <p>Automatic</p>
                        </label>
                    </div>
                </li>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="">
                        <label for="">
                            <p>Landline_dhcp</p>
                        </label>
                    </div>
                </li>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="">
                        <label for="">
                            <p>fx_corp</p>
                        </label>
                    </div>
                </li>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="">
                        <label for="">
                            <p>fx_crew</p>
                        </label>
                    </div>
                </li>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="">
                        <label for="">
                            <p>abcdefg1</p>
                        </label>
                    </div>
                </li>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="">
                        <label for="">
                            <p>abcdefg2</p>
                        </label>
                    </div>
                </li>
                <li>
                    <div class="radio v1">
                        <input type="radio" name="" id="">
                        <label for="">
                            <p>abcdefg3</p>
                        </label>
                    </div>
                </li>
            </ul>
        </div>

        <hr class="line v1 mt30">

        <p class="tit v1 mt30">Time duration</p>

        <select name="" id="" class="select v1 mt10">
            <option value="">5 minutes</option>
        </select>
    </div>
    <div class="pop-foot">
        <button class="btn md fill-mint" onclick="popClose('pop-set-terminal')"><i class="ic-submit"></i>APPLY</button>
        <button class="btn md fill-dark" onclick="popClose('pop-set-terminal')"><i class="ic-cancel"></i>CANCEL</button>
    </div>
</div>

</body>
</html>