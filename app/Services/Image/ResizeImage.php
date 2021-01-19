<?php

namespace App\Services\Image;

use Spatie\Image\Image;
use Illuminate\Support\Str;
use App\Services\BaseService;
use App\Helpers\InstanceHelper;
use App\Models\Company\Employee;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\FileDoesNotExistException;

class ResizeImage extends BaseService
{
    /**
     * The data the service is called with.
     */
    private array $data;

    /**
     * The disk the image is read from, and saved to.
     */
    private string $disk;

    /**
     * The name of the file, without the extension.
     */
    private string $baseName;

    /**
     * The actual file on disk.
     */
    private string $fileOnDisk;

    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'filename' => 'required|string', // contains the path
            'extension' => 'required|string',
            'width' => 'required|integer',
        ];
    }

    /**
     * Resize an image.
     *
     * @param array $data
     *
     * @return Employee
     */
    public function execute(array $data): void
    {
        $this->data = $data;
        $this->disk = InstanceHelper::getDefaultDisk();

        $this->validate();
        $this->resize();
    }

    private function validate(): void
    {
        $this->validateRules($this->data);

        // check if the image actually exists
        if (! Storage::disk($this->disk)->exists($this->data['filename'])) {
            throw new FileDoesNotExistException();
        }

        // get the actual file on disk for further manipulation
        //$this->fileOnDisk = Storage::disk($this->disk)->get($this->data['filename']);

        // get the base name of the file without the extension
        $this->baseName = Str::of($this->data['filename'])->basename($this->data['extension']);

        // check if the desired new image size already exists
    }

    private function resize(): void
    {
        $newFileName = $this->baseName.'-'.$this->data['width'].'.'.$this->data['extension'];

        Image::load(public_path($this->data['filename']))
            ->width($this->data['width'])
            ->save($newFileName);
    }
}
