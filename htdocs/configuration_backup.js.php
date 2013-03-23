<?php

/**
 * Javascript helper for Configuration_Backup.
 *
 * @category   Apps
 * @package    Configuration_Backup
 * @subpackage Javascript
 * @author     ClearCenter <developer@clearcenter.com>
 * @copyright  2011 ClearCenter
 * @license    http://www.clearcenter.com/app_license ClearCenter license
 * @link       http://www.clearcenter.com/support/documentation/clearos/configuration_backup/
 */

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('configuration_backup');
clearos_load_language('base');

///////////////////////////////////////////////////////////////////////////////
// J A V A S C R I P T
///////////////////////////////////////////////////////////////////////////////

header('Content-Type: application/x-javascript');

echo "

$(document).ready(function() {

    // CSS hack...Multipart form screw up width
    $('#upload_form').css('width', '100%');

    get_status();
    $('.ui-buttonset [href]').click(function (e) {
         if ($(this).hasClass('ui-state-disabled'))
             return false;
    });
});

function get_status() {
    $.ajax({
        type: 'GET',
        dataType: 'json',
        url: '/app/configuration_backup/get_restore_progress',
        data: '',
        success: function(data) {
            if (data == undefined || data.code == null) {
                window.setTimeout(get_status, 1000);
                return;
            }
                
            if (data.code < 0) {
                table_logs.fnClearTable();
            } else {
//                    $('.ui-buttonset :input').attr('disabled', false);
//                    $('.ui-buttonset :input').removeClass('ui-state-disabled');
//                    $('.ui-buttonset [href]').removeClass('ui-state-disabled');
//                    $('#configuration_backup_info_box').hide();
//                    $('.ui-buttonset :input').attr('disabled', true);
//                    $('#configuration_backup_info_box').show();
//                    $('#configuration_backup_details').html(data.status);
//                    $('.ui-buttonset :input').addClass('ui-state-disabled');
//                    $('.ui-buttonset [href]').addClass('ui-state-disabled');
//                    if (data.progress == 100)
//                        $('#configuration_backup_details').removeClass('theme-loading-normal');
                // Logs
                if (data.logs != undefined && data.logs != null && $('#logs').length > 0) {
                    table_logs.fnClearTable();
                    var progress = 0;
                    for (var index = 0 ; index < data.logs.length; index++) {
                        if (data.logs[index] == null)
                            continue;
                        date = new Date(data.logs[index].timestamp*1000);
                        span_tag = '<span>';
                        if (data.logs[index].code != 0)
                            span_tag = '<span style=\'color: red;\'>';
                        table_logs.fnAddData([
                            span_tag + data.logs[index].msg + '</span>',
                            span_tag + $.datepicker.formatDate('M d, yy', date) + ' ' + date.toLocaleTimeString() + '</span>'
                        ]);
                        if (index == 0) {
                            $('#progress').progressbar({
                                value: Math.round(data.logs[index].progress)
                            });
                        }
                    }
                    table_logs.fnAdjustColumnSizing();
                    table_logs.each(function(){
                        $(this).find('td:eq(1)').attr('nowrap', 'nowrap');
                    });
                }
                window.setTimeout(get_status, 1000);
            }
        },
        error: function(xhr, text, err) {
            // Don't display any errors if ajax request was aborted due to page redirect/reload
            if (xhr['abort'] == undefined)
                clearos_alert('errmsg', xhr.responseText.toString());
            window.setTimeout(get_status, 1000);
        }
    });
}
";

// vim: syntax=javascript ts=4
