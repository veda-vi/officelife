<?php

namespace App\Services\Company\Employee\Holiday;

use App\Services\BaseService;
use App\Models\Company\Employee;
use App\Models\Company\EmployeePlannedHoliday;

class DestroyTimeOff extends BaseService
{
    private EmployeePlannedHoliday $holiday;

    private Employee $employee;

    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'author_id' => 'required|integer|exists:employees,id',
            'company_id' => 'required|integer|exists:companies,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'employee_planned_holiday_id' => 'required|integer|exists:employee_planned_holidays,id',
        ];
    }

    /**
     * Get the data to log after the service has been executed.
     *
     *
     * @return array
     */
    public function logs(): array
    {
        return [
            'action' => 'time_off_destroyed',
            'employee_id' => $this->employee->id,
            'objects_to_log' => [
                'planned_holiday_date' => $this->holiday->planned_date,
            ],
        ];
    }

    /**
     * Destroy a planned holiday.
     *
     * @param array $data
     *
     * @return bool
     */
    public function execute(array $data): bool
    {
        $this->validateRules($data);

        $this->author($data['author_id'])
            ->inCompany($data['company_id'])
            ->asAtLeastHR()
            ->canBypassPermissionLevelIfEmployee($data['employee_id'])
            ->canExecuteService();

        $this->employee = $this->validateEmployeeBelongsToCompany($data);

        $this->holiday = EmployeePlannedHoliday::findOrFail($data['employee_planned_holiday_id']);
        $this->holiday->delete();

        $this->addAuditLog();
        $this->addEmployeeLog();

        return true;
    }
}
