<?php

namespace App\Services\Company\Employee\Birthdate;

use Carbon\Carbon;
use App\Jobs\LogAccountAudit;
use App\Services\BaseService;
use App\Jobs\LogEmployeeAudit;
use App\Models\Company\Employee;
use App\Interfaces\ServiceInterface;
use Carbon\Exceptions\InvalidDateException;

class SetBirthdate extends BaseService implements ServiceInterface
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
        $this->addAuditLog();

        return $this->employee;
    }

    private function checkDateValidity(array $data): void
    {
        try {
            $carbonObject = Carbon::createSafe($data['year'], $data['month'], $data['day']);
        } catch (InvalidDateException $e) {
            throw new \Exception(trans('app.error_invalid_date'));
        }

        $this->birthdate = $carbonObject;
    }

    private function save(): void
    {
        $this->employee->birthdate = $this->birthdate;
        $this->employee->save();
    }

    private function log(array $data, Carbon $date): void
    {
        LogAccountAudit::dispatch([
            'company_id' => $data['company_id'],
            'action' => 'employee_birthday_set',
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
            'audited_at' => Carbon::now(),
            'objects' => json_encode([
                'employee_id' => $this->employee->id,
                'employee_name' => $this->employee->name,
                'birthday' => $date->format('Y-m-d'),
            ]),
            'is_dummy' => $this->valueOrFalse($data, 'is_dummy'),
        ])->onQueue('low');

        LogEmployeeAudit::dispatch([
            'employee_id' => $data['employee_id'],
            'action' => 'birthday_set',
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
            'audited_at' => Carbon::now(),
            'objects' => json_encode([
                'birthday' => $date->format('Y-m-d'),
            ]),
            'is_dummy' => $this->valueOrFalse($data, 'is_dummy'),
        ])->onQueue('low');
    }
}
