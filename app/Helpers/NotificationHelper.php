<?php

namespace App\Helpers;

use App\Models\Company\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\Company\Notification;

class NotificationHelper
{
    /**
     * Returns the notifications for this employee.
     *
     * @param Employee $employee
     *
     * @return Collection
     */
    public static function getNotifications(Employee $employee): Collection
    {
        // $notifs = $employee->notifications()
        //     ->where('read', false)
        //     ->orderBy('created_at', 'desc')
        //     ->get();
        $notifs = DB::table('notifications')
            ->select('id', 'action', 'objects', 'read')
            ->where('read', false)
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->get();

        $notificationCollection = collect([]);
        foreach ($notifs as $notification) {
            $notificationCollection->push([
                'id' => $notification->id,
                'action' => $notification->action,
                'localized_content' => self::process($notification->action, $notification->objects),
                'read' => $notification->read,
            ]);
        }

        return $notificationCollection;
    }

    /**
     * Return an sentence explaining what the notification contains.
     *
     * @param string $action
     * @param string $objects
     *
     * @return string
     */
    public static function process(string $action, string $objects): string
    {
        $objects = json_decode($objects);

        switch ($action) {
            case 'dummy_data_generated':
                $sentence = trans('account.notification_dummy_data_generated', [
                    'name' => $objects->{'company_name'},
                ]);
                break;

            case 'employee_added_to_company':
                $sentence = trans('account.notification_employee_added_to_company', [
                    'name' => $objects->{'company_name'},
                ]);
                break;

            case 'employee_added_to_team':
                $sentence = trans('account.notification_employee_added_to_team', [
                    'name' => $objects->{'team_name'},
                ]);
                break;

            case 'employee_removed_from_team':
                $sentence = trans('account.notification_employee_removed_from_team', [
                    'name' => $objects->{'team_name'},
                ]);
                break;

            case 'team_lead_set':
                $sentence = trans('account.notification_team_lead_set', [
                    'name' => $objects->{'team_name'},
                ]);
                break;

            case 'team_lead_removed':
                $sentence = trans('account.notification_team_lead_removed', [
                    'name' => $objects->{'team_name'},
                ]);
                break;

            case 'employee_attached_to_recent_ship':
                $sentence = trans('account.notification_employee_attached_to_recent_ship', [
                    'title' => $objects->{'ship_title'},
                ]);
                break;

            case 'task_assigned':
                $sentence = trans('account.notification_task_assigned', [
                    'title' => $objects->{'title'},
                    'name' => $objects->{'author_name'},
                ]);
                break;

            case 'expense_assigned_for_validation':
                $sentence = trans('account.notification_expense_assigned_for_validation', [
                    'name' => $objects->{'name'},
                ]);
                break;

            case 'expense_accepted_by_manager':
                $sentence = trans('account.notification_expense_accepted_by_manager', [
                    'title' => $objects->{'title'},
                ]);
                break;

            case 'expense_rejected_by_manager':
                $sentence = trans('account.notification_expense_rejected_by_manager', [
                    'title' => $objects->{'title'},
                ]);
               break;

            case 'expense_accepted_by_accounting':
                $sentence = trans('account.notification_expense_accepted_by_accounting', [
                    'title' => $objects->{'title'},
                ]);
                break;

            case 'expense_rejected_by_accounting':
                $sentence = trans('account.notification_expense_rejected_by_accounting', [
                    'title' => $objects->{'title'},
                ]);
                break;

            case 'employee_allowed_to_manage_expenses':
                $sentence = trans('account.notification_employee_allowed_to_manage_expenses', []);
                break;

            default:
                $sentence = '';
                break;
        }

        return $sentence;
    }
}
