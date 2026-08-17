<?php

namespace App\Http\Requests;

use App\Models\App;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_force_update' => $this->boolean('is_force_update'),
        ]);
    }

    public function rules(): array
    {
        return [
            'apk' => [
                'required',
                'file',
                'extensions:apk',
                // An APK is a ZIP container, so servers report it under a
                // handful of different types depending on the client.
                'mimetypes:application/vnd.android.package-archive,application/zip,'
                    .'application/x-zip-compressed,application/java-archive,application/octet-stream',
                'max:'.config('apk.max_upload_kb'),
            ],
            'version_name' => ['required', 'string', 'max:50'],
            'version_code' => [
                'required',
                'integer',
                'min:1',
                // MySQL unsigned int ceiling.
                'max:4294967295',
                $this->versionCodeMustIncrease(),
            ],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'is_force_update' => ['boolean'],
        ];
    }

    /**
     * A new build must carry a strictly higher version_code than anything the
     * app has ever published — including pulled (inactive) builds, since
     * handsets in the field may already be running one of those.
     */
    private function versionCodeMustIncrease(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $app = $this->route('app');

            if (! $app instanceof App) {
                return;
            }

            $current = $app->maxVersionCode();

            if ($current > 0 && (int) $value <= $current) {
                $fail("The version code must be greater than the current highest ({$current}).");
            }
        };
    }

    public function attributes(): array
    {
        return [
            'apk' => 'APK file',
            'version_name' => 'version name',
            'version_code' => 'version code',
        ];
    }

    public function messages(): array
    {
        return [
            'apk.extensions' => 'The upload must be an .apk file.',
            'apk.mimetypes' => 'The upload does not look like a valid APK package.',
            'apk.max' => 'The APK may not be larger than :max kilobytes. Check upload_max_filesize and post_max_size in php.ini.',
        ];
    }
}
