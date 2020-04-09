<?php

namespace App\Services\Company\Adminland\CompanyPTOPolicy;

use Carbon\Carbon;
use App\Helpers\DateHelper;
use App\Services\BaseService;
use App\Models\Company\CompanyCalendar;
use App\Models\Company\CompanyPTOPolicy;
use App\Exceptions\CompanyPTOPolicyAlreadyExistException;

class CreateCompanyPTOPolicy extends BaseService
{
    private CompanyPTOPolicy $ptoPolicy;
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
            'year' => 'required|date_format:Y',
            'default_amount_of_allowed_holidays' => 'required|integer',
            'default_amount_of_sick_days' => 'required|integer',
            'default_amount_of_pto_days' => 'required|integer',
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
            'action' => 'company_pto_policy_created',
            'objects_to_log' => [
                'company_pto_policy_id' => $this->ptoPolicy->id,
                'company_pto_policy_year' => $this->ptoPolicy->year,
            ],
        ];
    }

    /**
     * Create a company PTO policy.
     *
     * @param array $data
     *
     * @return CompanyPTOPolicy
     */
    public function execute(array $data): CompanyPTOPolicy
    {
        $this->validateRules($data);

        $this->author($data['author_id'])
            ->inCompany($data['company_id'])
            ->asAtLeastHR()
            ->canExecuteService();

        // check if there is a policy for the given year already
        $existingPolicy = CompanyPTOPolicy::where('company_id', $data['company_id'])
            ->where('year', $data['year'])
            ->first();

        if ($existingPolicy) {
            // that's bad.
            throw new CompanyPTOPolicyAlreadyExistException();
        }

        $this->ptoPolicy = CompanyPTOPolicy::create([
            'company_id' => $data['company_id'],
            'year' => $data['year'],
            'total_worked_days' => 261,
            'default_amount_of_allowed_holidays' => $data['default_amount_of_allowed_holidays'],
            'default_amount_of_sick_days' => $data['default_amount_of_sick_days'],
            'default_amount_of_pto_days' => $data['default_amount_of_pto_days'],
            'is_dummy' => $this->valueOrFalse($data, 'is_dummy'),
        ]);

        // fix the number of worked days to be sure
        $offDays = $this->populateCalendar($data);
        $numberOfWorkedDays = DateHelper::getNumberOfDaysInYear(Carbon::now()) - $offDays;
        $this->ptoPolicy->total_worked_days = $numberOfWorkedDays;
        $this->ptoPolicy->save();

        $this->addAuditLog();

        return $this->ptoPolicy;
    }

    /**
     * Populate the calendar for the entire year with the days. By default it
     * will contain all the days. Weekends will be marked as non-working days.
     * Right after, employers will be able to identify which days are holidays
     * and therefore considered as being off.
     *
     * @param array $data
     *
     * @return int
     */
    private function populateCalendar(array $data): int
    {
        $day = Carbon::create($data['year']);
        $numberOfDaysOff = 0;

        for ($counter = 1; $counter <= DateHelper::getNumberOfDaysInYear($day); $counter++) {
            $isWorked = true;
            if ($day->isSaturday() || $day->isSunday()) {
                $isWorked = false;
                $numberOfDaysOff++;
            }

            CompanyCalendar::create([
                'company_pto_policy_id' => $this->ptoPolicy->id,
                'day' => $day->format('Y-m-d'),
                'day_of_week' => $day->dayOfWeek,
                'day_of_year' => $day->dayOfYear,
                'is_worked' => $isWorked,
            ]);

            $day->addDay();
        }

        return $numberOfDaysOff;
    }
}
