<?php

namespace App\Services\Company\Adminland\CompanyNews;

use App\Services\BaseService;
use App\Models\Company\Employee;
use App\Models\Company\CompanyNews;
use App\Interfaces\ServiceInterface;

class CreateCompanyNews extends BaseService implements ServiceInterface
{
    private CompanyNews $news;

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
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:65535',
            'created_at' => 'nullable|date_format:Y-m-d H:i:s',
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
            'action' => 'company_news_created',
            'objects_to_log' => [
                'company_news_id' => $this->news->id,
                'company_news_title' => $this->news->title,
            ],
        ];
    }

    /**
     * Create a company news.
     *
     * @param array $data
     *
     * @return CompanyNews
     */
    public function execute(array $data): CompanyNews
    {
        $this->validateRules($data);

        $this->author($data['author_id'])
            ->inCompany($data['company_id'])
            ->asAtLeastHR()
            ->canExecuteService();

        $author = Employee::find($data['author_id']);

        $this->news = CompanyNews::create([
            'company_id' => $data['company_id'],
            'author_id' => $data['author_id'],
            'author_name' => $author->name,
            'title' => $data['title'],
            'content' => $data['content'],
            'is_dummy' => $this->valueOrFalse($data, 'is_dummy'),
        ]);

        if (! empty($data['created_at'])) {
            $this->news->created_at = $data['created_at'];
            $this->news->save();
        }

        $this->addAuditLog();

        return $this->news;
    }
}
