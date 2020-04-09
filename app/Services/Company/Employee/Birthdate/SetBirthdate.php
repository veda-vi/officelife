<?php

namespace App\Services\Company\Employee\Birthdate;

use Carbon\Carbon;
use App\Services\BaseService;
use App\Models\Company\Employee;
use Carbon\Exceptions\InvalidDateException;

class SetBirthdate extends BaseService
{
    private Employee $employee;

    private Carbon $birthdate;

    /**
     * Get the validation rules that apply to the service.
     */
    public function rules(): array
    {
        return [
            'company_id' => 'required|integer|exists:companies,id',
            'author_id' => 'required|integer|exists:employees,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'year' => 'required|integer',
            'month' => 'required|integer',
            'day' => 'required|integer',
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
            'action' => 'employee_birthday_set',
            'employee_id' => $this->employee->id,
            'objects_to_log' => [
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->name,
                'birthday' => $this->birthdate->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Set the birthdate of an employee.
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

        $this->checkDateValidity($data);

        $this->save();

        $this->addAuditLog();
        $this->addEmployeeLog();

        return $this->employee;
    }

    /**
     * Make sure that the given date is a valid Carbon date.
     *
     * @param array $data
     */
    private function checkDateValidity(array $data): void
    {
        try {
            $carbonObject = Carbon::createSafe($data['year'], $data['month'], $data['day']);
        } catch (InvalidDateException $e) {
            throw new \Exception(trans('app.error_invalid_date'));
        }

        $this->birthdate = $carbonObject;
    }

    /**
     * Save the data.
     *
     */
    private function save(): void
    {
        $this->employee->birthdate = $this->birthdate;
        $this->employee->save();
    }
}
