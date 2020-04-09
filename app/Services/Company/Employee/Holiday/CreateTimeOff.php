<?php

namespace App\Services\Company\Employee\Holiday;

use Exception;
use Carbon\Carbon;
use App\Services\BaseService;
use App\Helpers\HolidayHelper;
use Illuminate\Validation\Rule;
use App\Models\Company\Employee;
use App\Models\Company\EmployeePlannedHoliday;

class CreateTimeOff extends BaseService
{
    private Employee $employee;

    private EmployeePlannedHoliday $plannedHoliday;

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
            'date' => 'required|date_format:Y-m-d',
            'type' => 'required',
                Rule::in([
                    'holiday',
                    'sick',
                    'pto',
                ]),
            'full' => 'required|boolean',
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
            'action' => 'time_off_created',
            'employee_id' => $this->employee->id,
            'objects_to_log' => [
                'planned_holiday_id' => $this->plannedHoliday->id,
                'planned_holiday_date' => $this->plannedHoliday->planned_date,
            ],
        ];
    }

    /**
     * Log a time off for the given employee.
     * A time off can only be of two types: half day or full day.
     * For any given day you can therefore either be a full day, or two half
     * days. We will not put in place rules against the types of PTO someone
     * wants to take. That means he can take one half day of sick day, and the
     * other half day as holiday, for instance.
     *
     * @param array $data
     *
     * @return EmployeePlannedHoliday
     */
    public function execute(array $data): EmployeePlannedHoliday
    {
        $this->validateRules($data);

        $this->employee = $this->validateEmployeeBelongsToCompany($data);

        $this->author($data['author_id'])
            ->inCompany($data['company_id'])
            ->asAtLeastHR()
            ->canBypassPermissionLevelIfEmployee($data['employee_id'])
            ->canExecuteService();

        $suggestedDate = Carbon::parse($data['date']);

        // grab the PTO policy and check wether this day is a worked day or not
        $ptoPolicy = $this->employee->company->getCurrentPTOPolicy();
        if (! HolidayHelper::isDayWorkedForCompany($ptoPolicy, $suggestedDate)) {
            throw new Exception('The day is considered worked for the company');
        }

        // check if an holiday already exists for this day
        // If the date is already taken as a planned holiday in full, we can't take
        // this day as it’s already taken.
        // If the date is already taken but as half, it means we can take it but
        // only as a half day.
        $existingPlannedHoliday = $this->getExistingPlannedHoliday($suggestedDate);
        $plannedHoliday = '';

        if ($existingPlannedHoliday) {
            if ($this->validateCreationHoliday($existingPlannedHoliday, $data)) {
                $this->createPlannedHoliday($data, $suggestedDate);
            }
        } else {
            $this->createPlannedHoliday($data, $suggestedDate);
        }

        return $this->plannedHoliday;
    }

    /**
     * Get the planned holiday object for this date, if it already exists.
     *
     * @param Carbon $date
     *
     * @return EmployeePlannedHoliday
     */
    private function getExistingPlannedHoliday(Carbon $date)
    {
        $holiday = EmployeePlannedHoliday::where('employee_id', $this->employee->id)
            ->where('planned_date', $date->format('Y-m-d 00:00:00'))
            ->count();

        if ($holiday > 1) {
            throw new Exception();
        }

        $holiday = EmployeePlannedHoliday::where('employee_id', $this->employee->id)
            ->where('planned_date', $date->format('Y-m-d 00:00:00'))
            ->first();

        return $holiday;
    }

    /**
     * Validate wether we can create a new holiday.
     *
     * @param EmployeePlannedHoliday $holiday
     * @param array                  $data
     *
     * @return bool
     */
    private function validateCreationHoliday(EmployeePlannedHoliday $holiday, array $data): bool
    {
        // we can't log any new holiday - the day is already used
        if ($holiday->full) {
            throw new Exception();
        }

        // here, we are in the case of a half day, but the person requested
        // a full day
        if ($data['full']) {
            throw new Exception();
        }

        return true;
    }

    /**
     * Create a new planned holiday.
     *
     * @param array  $data
     * @param Carbon $date
     */
    private function createPlannedHoliday(array $data, Carbon $date): void
    {
        $this->plannedHoliday = EmployeePlannedHoliday::create([
            'employee_id' => $data['employee_id'],
            'planned_date' => $date,
            'type' => $data['type'],
            'full' => $data['full'],
        ]);
    }
}
