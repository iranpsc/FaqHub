<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\HtmlSanitizer;

class UpdateQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('question'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:50000', // Limit content size
            'tags' => 'present|array|max:10', // Limit number of tags
            'tags.*' => 'required|array',
            'tags.*.id' => 'required_without:tags.*.name|exists:tags,id',
            'tags.*.name' => 'required_without:tags.*.id|string|max:50',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize the title (strip all HTML)
        if ($this->has('title')) {
            $this->merge([
                'title' => strip_tags($this->input('title')),
            ]);
        }
    }

    /**
     * Get the validated data with sanitized content.
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        // If getting all validated data, sanitize content
        if ($key === null && isset($validated['content'])) {
            $sanitizer = app(HtmlSanitizer::class);
            $validated['content'] = $sanitizer->sanitize($validated['content']);
        }

        return $validated;
    }
}
