<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Company\Team;
use App\Jobs\LogAccountAudit;
use App\Jobs\LogEmployeeAudit;
use App\Models\Company\Employee;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\NotEnoughPermissionException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class BaseService
{
    /**
     * The author (who is an Employee) who calls the service.
     */
    private Employee $author;

    /**
     * The author ID of the employee who calls the service.
     * Used to populate the author object above.
     */
    private int $authorId;

    /**
     * The id of the company the service is supposed to be executed into.
     */
    private int $companyId;

    /**
     * The minimum permission level required to process the service for the
     * employee who triggers it.
     */
    private int $requiredPermissionLevel;

    /**
     * Indicates whether the employee can bypass the minimum required permission
     * level to execute the service.
     */
    private bool $bypassRequiredPermissionLevel = false;

    /**
     * Indicates whether the data that is about to get logged is dummy.
     */
    private bool $isDummy = false;

    /**
     * Get the validation rules that apply to the service.
     */
    abstract public function rules();

    /**
     * Get the data to log after the service has been executed.
     */
    abstract public function logs();

    /**
     * Sets the author id for the service.
     *
     * @param integer $givenAuthor
     *
     * @return self
     */
    public function author(int $givenAuthor): self
    {
        $this->authorId = $givenAuthor;
        return $this;
    }

    /**
     * Gets the author id for the service.
     *
    *
     * @return int
     */
    public function getAuthor(): int
    {
        return $this->authorId;
    }

    /**
     * Sets the company id for the service.
     *
     * @param integer $company
     *
     * @return self
     */
    public function inCompany(int $company): self
    {
        $this->companyId = $company;
        return $this;
    }

    /**
     * Sets the company id for the service.
     *
     * @return int
     */
    public function getCompany(): int
    {
        return $this->companyId;
    }

    /**
     * Gets the permission level.
     *
     * @return self
     */
    public function getPermissionLevel(): int
    {
        return $this->requiredPermissionLevel;
    }

    /**
     * Sets the permission level required for this service.
     *
     * @return self
     */
    public function asAtLeastAdministrator(): self
    {
        $this->requiredPermissionLevel = config('officelife.permission_level.administrator');
        return $this;
    }

    /**
     * Sets the permission level required for this service.
     *
     * @return self
     */
    public function asAtLeastHR(): self
    {
        $this->requiredPermissionLevel = config('officelife.permission_level.hr');
        return $this;
    }

    /**
     * Sets the permission level required for this service.
     *
     * @return self
     */
    public function asNormalUser(): self
    {
        $this->requiredPermissionLevel = config('officelife.permission_level.user');
        return $this;
    }

    /**
     * Gets the information about the employee having the right to execute the
     * service regardless of the permission level.
     *
     *
     * @return bool
     */
    public function getBypassPermissionLevelFlag(): bool
    {
        return $this->bypassRequiredPermissionLevel;
    }

    /**
     * Sets the permission to bypass the minimum level requirement.
     *
     * @param integer $employeeId
     *
     * @return self
     */
    public function canBypassPermissionLevelIfEmployee(int $employeeId): self
    {
        $this->bypassRequiredPermissionLevel = ($this->authorId == $employeeId);
        return $this;
    }

    /**
     * Gets the dummy information.
     *
     * @return bool
     */
    public function getDummyStatus(): bool
    {
        return $this->isDummy;
    }

    /**
     * Indicates that the audit log should be logged as dummy.
     *
     * @return self
     */
    public function withDummyData(): self
    {
        $this->isDummy = true;
        return $this;
    }

    /**
     * Validate an array against a set of rules.
     *
     * @param array $data
     *
     * @return bool
     */
    public function validateRules(array $data): bool
    {
        Validator::make($data, $this->rules())
                ->validate();

        return true;
    }

    /**
     * Check that the employee effectively belongs to the given company.
     */
    public function validateEmployeeBelongsToCompany(array $data): Employee
    {
        try {
            $employee = Employee::where('company_id', $data['company_id'])
                ->findOrFail($data['employee_id']);
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException(trans('app.error_wrong_employee_id'));
        }

        return $employee;
    }

    /**
     * Check that the team effectively belongs to the given company.
     */
    public function validateTeamBelongsToCompany(array $data): Team
    {
        try {
            $team = Team::where('company_id', $data['company_id'])
                ->findOrFail($data['team_id']);
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException(trans('app.error_wrong_team_id'));
        }

        return $team;
    }

    /**
     * Checks if the employee executing the service has the permission
     * to do the action.
     *
     * @return bool
     */
    public function canExecuteService(): bool
    {
        try {
            $this->author = Employee::where('company_id', $this->companyId)
                ->where('id', $this->authorId)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException(trans('app.error_wrong_employee_id'));
        }

        if ($this->bypassRequiredPermissionLevel) {
            return true;
        }

        if ($this->requiredPermissionLevel < $this->author->permission_level) {
            throw new NotEnoughPermissionException;
        }

        return true;
    }

    /**
     * Checks if the value is empty or null.
     *
     * @param mixed $data
     * @param mixed $index
     *
     * @return mixed
     */
    public function valueOrNull($data, $index)
    {
        if (empty($data[$index])) {
            return;
        }

        return $data[$index] == '' ? null : $data[$index];
    }

    /**
     * Checks if the value is empty or null and returns a date from a string.
     *
     * @param mixed $data
     * @param mixed $index
     *
     * @return mixed
     */
    public function nullOrDate($data, $index)
    {
        if (empty($data[$index])) {
            return;
        }

        return $data[$index] == '' ? null : Carbon::parse($data[$index]);
    }

    /**
     * Returns the value if it's defined, or false otherwise.
     *
     * @param mixed $data
     * @param mixed $index
     *
     * @return mixed
     */
    public function valueOrFalse($data, $index)
    {
        if (empty($data[$index])) {
            return false;
        }

        return $data[$index];
    }

    /**
     * Create an account audit log.
     *
     */
    public function addAuditLog(): void
    {
        $data = $this->logs();

        LogAccountAudit::dispatch([
            'company_id' => $this->companyId,
            'action' => $data['action'],
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
            'audited_at' => Carbon::now(),
            'objects' => json_encode($data['objects_to_log']),
            'is_dummy' => $this->isDummy,
        ])->onQueue('low');
    }

    /**
     * Create an employee audit log.
     *
     */
    public function addEmployeeLog(): void
    {
        $data = $this->logs();

        LogEmployeeAudit::dispatch([
            'employee_id' => $data['employee_id'],
            'action' => $data['action'],
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
            'audited_at' => Carbon::now(),
            'objects' => json_encode($data['objects_to_log']),
            'is_dummy' => $this->isDummy,
        ])->onQueue('low');
    }
}
