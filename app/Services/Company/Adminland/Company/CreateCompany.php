<?php

namespace App\Services\Company\Adminland\Company;

use App\Models\User\User;
use Illuminate\Support\Str;
use App\Services\BaseService;
use App\Models\Company\Company;
use App\Models\Company\Employee;
use App\Interfaces\ServiceInterface;
use App\Services\User\Avatar\GenerateAvatar;

class CreateCompany extends BaseService implements ServiceInterface
{
    private Company $company;
    private Employee $employee;

    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'author_id' => 'required|integer|exists:users,id',
            'name' => 'required|unique:companies,name|string|max:255',
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
            'action' => 'account_created',
            'objects_to_log' => [
                'company_name' => $this->company->name,
            ],
        ];
    }

    /**
     * Create a company.
     *
     * @param array $data
     *
     * @return Company
     */
    public function execute(array $data): Company
    {
        $this->validateRules($data);

        $this->company = Company::create([
            'name' => $data['name'],
        ]);

        $user = User::find($data['author_id']);

        $this->addFirstEmployee($user);
        $this->provisionDefaultAccountData();

        // add holidays for the newly created employee
        $this->employee->amount_of_allowed_holidays = $this->company->getCurrentPTOPolicy()->default_amount_of_allowed_holidays;
        $this->employee->save();

        $this->addAuditLog();

        return $this->company;
    }

    /**
     * Add the first employee to the company.
     *
     * @param User $user
     *
     */
    private function addFirstEmployee(User $user): void
    {
        $uuid = Str::uuid()->toString();

        $avatar = (new GenerateAvatar)->execute([
            'uuid' => $uuid,
            'size' => 200,
        ]);

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'uuid' => Str::uuid()->toString(),
            'permission_level' => config('officelife.permission_level.administrator'),
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => $avatar,
        ]);
    }

    /**
     * Provision the newly created account with default data.
     *
     *
     */
    private function provisionDefaultAccountData(): void
    {
        (new ProvisionDefaultAccountData)->execute([
            'company_id' => $this->company->id,
            'author_id' => $this->employee->id,
        ]);
    }
}
