<?php

use WHMCS\Session;
use WHMCS\User\Admin;
Use WHMCS\Support\Ticket;
use WHMCS\Database\Capsule;

/**
 * Automatically Mark Ticket for Support Agent
 *
 * A helper that automatically marks a ticket in progress and for the current support agent either by
 * pressing the "Mark for me" button or by typing in the reply textbox. 
 * 
 * Both of these can be controlled by setting the relevant variables 
 * $showButton and $onTextChange to true or false below.
 *
 * @package    WHMCS
 * @author     Lee Mahoney <lee@leemahoney.dev>
 * @copyright  Copyright (c) Lee Mahoney 2022
 * @license    MIT License
 * @version    1.0.2
 * @link       https://leemahoney.dev
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function auto_mark_ticket($vars) {

    # Set to true to show a "Mark for me" button beside the Close button
    $showButton = true;

    # Set to true to automatically change "Status" and "Assigned To" when the support agent begins typing
    $onTextChange = true;

    # Grab current admin ID
    $adminId = Session::get('adminid');
    
    # Make sure it exists
    if (!$adminId) return;

    # Only execute the rest of the code if either variable above are marked true
    if ($showButton || $onTextChange) {

        # Need some way to update the database
        if ($_GET['a'] == 'markforme') {

            # Check the admin logged in has access to the support department
            # Only because this can be accessed directly 
            # whmcs should stop this, but better to be safe.
            
            # Get the admins support departments
            $getAdminSupportDepartments = Admin::where('id', $adminId)->first();
            $adminSupportDepartments    = explode(',', $getAdminSupportDepartments->supportdepts);

            # Get the tickets support department
            $ticketDetails      = Ticket::where('id', $vars['ticketid'])->first();
            $ticketDepartment   = $ticketDetails->deptid;

            # Check if the support department is in the users list of authorized departments
            if (in_array($ticketDepartment, $adminSupportDepartments)) {

                Ticket::where('id', $vars['ticketid'])->update([
                    'status'    => 'In Progress',
                    'flag'      => $adminId
                ]);

                die(json_encode([
                    'result' => 'success'
                ]));

            } else {

                die(json_encode([
                    'result' => 'error',
                    'Admin is not part of the support department that this ticket is under'
                ]));

            }

        }

        # URL shows as encoded, let's fix that or else the get request wont work
        $url = str_replace("&amp;", "&", $_SERVER['REQUEST_URI']);

        # Start the JavaScript
        $script = "
        <script type='text/javascript'>
            $(document).ready(function () {
        ";

        # Only execute this portion of the script if the $showButton variable is true
        if ($showButton) {

            $script .= "

                $('.ticket-subject').append(' &nbsp;&nbsp;<a href=\"#\" id=\"markforme\" class=\"btn btn-warning\">Mark for me</a>');
            
                $('#markforme').on('click', function (e) {
                    e.preventDefault();
                    markForMe();
                });

            ";

        }

        # Only execute this portion of the script if the $onTextChange variable is true
        if ($onTextChange) {

            $script .= "
                $('#replymessage').on('keydown', function () {
                    markForMe();
                });
            ";

        }
        
        # Finish the script and return it                         
        $script .= "
                function markForMe() {
                    $.get('{$url}&a=markforme', function (data) {
                        data = $.parseJSON(data);
                        if (data.result == 'success') {
                            $('#ticketstatus').val('In Progress');
                            $('#flagto').val('{$adminId}');
                        } else if (data.result == 'error') {
                            console.log('Mark For Me Error: ' + data.message);
                        }
                    });
                }

            });
        </script>
        ";
                
        return $script;

    }

}

# Add the hook
add_hook('AdminAreaViewTicketPage', 1, 'auto_mark_ticket');
