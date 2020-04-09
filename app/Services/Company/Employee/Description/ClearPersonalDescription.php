<?php

namespace App\Services\Company\Employee\Description;

use App\Services\BaseService;
use App\Models\Company\Employee;

class ClearPersonalDescription extends BaseService
{
    private Employee $employee;

    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules(): array
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
            'action' => 'employee_description_cleared',
            'employee_id' => $this->employee->id,
            'objects_to_log' => [
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->name,
            ],
        ];
    }

    /**
     * Clear an employee's description.
     *
     * @param array $data
     *
     * @return Employee
     */
    public function execute(array $data): Employee
    {
        $this->validateRules($data);

        $this->author($data['author_id'])
            ->inCompany($data['company_id'])
            ->asAtLeastHR()
            ->canBypassPermissionLevelIfEmployee($data['employee_id'])
            ->canExecuteService();

        $this->employee = $this->validateEmployeeBelongsToCompany($data);

        $this->employee->description = null;
        $this->employee->save();

        $this->addAuditLog();
        $this->addEmployeeLog();

        return $this->employee;
    }
}
