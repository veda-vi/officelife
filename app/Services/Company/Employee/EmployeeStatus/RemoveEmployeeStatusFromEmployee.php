<?php

namespace App\Services\Company\Employee\EmployeeStatus;

use App\Services\BaseService;
use App\Models\Company\Employee;
use App\Models\Company\EmployeeStatus;

class RemoveEmployeeStatusFromEmployee extends BaseService
{
    private Employee $employee;

    private EmployeeStatus $status;

    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules():array
    {
        return [
            'company_id' => 'required|integer|exists:companies,id',
            'author_id' => 'required|integer|exists:employees,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'is_dummy' => 'nullable|boolean',
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
            'action' => 'employee_status_removed',
            'employee_id' => $this->employee->id,
            'objects_to_log' => [
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->name,
                'employee_status_id' => $this->status->id,
                'employee_status_name' => $this->status->name,
            ],
        ];
    }

    /**
     * Remove an employee's status.
     *
     * @param array $data
     *
     * @return Employee
     */
    public function execute(array $data):Employee
    {
        $this->validateRules($data);

        $this->author($data['author_id'])
            ->inCompany($data['company_id'])
            ->asAtLeastHR()
            ->canExecuteService();

        $this->employee = $this->validateEmployeeBelongsToCompany($data);

        $this->status = $this->employee->status;

        $this->employee->employee_status_id = null;
        $this->employee->save();
        $this->employee->refresh();

        $this->addAuditLog();
        $this->addEmployeeLog();

        return $this->employee;
    }
}
