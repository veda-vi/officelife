<?php

namespace App\Services\Company\Adminland\Company;

use Illuminate\Support\Str;
use App\Services\BaseService;
use App\Models\Company\Company;
use App\Models\Company\Employee;
use App\Interfaces\ServiceInterface;

class AddUserToCompany extends BaseService implements ServiceInterface
{
    protected Employee $employee;

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
            'user_id' => 'required|integer|exists:users,id',
            'permission_level' => 'required|integer',
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
            'action' => 'user_added_to_company',
            'objects_to_log' => [
                'user_id' => $this->employee->user->id,
                'user_email' => $this->employee->user->email,
            ],
        ];
    }

    /**
     * Add a user to the company.
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
            ->canExecuteService();

        $this->employee = Employee::create([
            'user_id' => $data['user_id'],
            'company_id' => $data['company_id'],
            'uuid' => Str::uuid()->toString(),
            'permission_level' => $data['permission_level'],
            'is_dummy' => $this->valueOrFalse($data, 'is_dummy'),
        ]);

        $this->addAuditLog();

        return $this->employee;
    }
}
