<?php

namespace App\Http\Requests\Whatsapp;

use App\Services\Whatsapp\Chat\MediaScanner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Body of POST conversations/{id}/messages (JSON, or multipart when a file is attached). Authorization is the route's permission middleware. */
class SendChatMessageRequest extends FormRequest
{
    public const TYPES = ['text', 'image', 'video', 'audio', 'ptt', 'document', 'sticker', 'location', 'contact', 'poll'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $media = in_array($this->input('type'), ['image', 'video', 'audio', 'ptt', 'document', 'sticker'], true);

        return [
            'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'text' => ['required_if:type,text', 'nullable', 'string', 'max:'.config('whatsapp_chat.max_text')],
            'caption' => ['nullable', 'string', 'max:1024'],
            'file' => $media ? ['required', 'file'] : ['prohibited'],
            'reply_to_id' => ['nullable', 'integer'],
            'location' => ['required_if:type,location', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'location.name' => ['nullable', 'string', 'max:150'],
            'location.address' => ['nullable', 'string', 'max:300'],
            'contacts' => ['required_if:type,contact', 'array', 'min:1', 'max:10'],
            'contacts.*.name' => ['required', 'string', 'max:100'],
            'contacts.*.phone' => ['required', 'string', 'regex:/^\+?[0-9 ()\-]{5,25}$/'],
            'poll' => ['required_if:type,poll', 'array'],
            'poll.name' => ['required_with:poll', 'string', 'max:255'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:12'],
            'poll.options.*' => ['required', 'string', 'max:100', 'distinct'],
            'poll.selectable_count' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $type = $this->input('type');
            $file = $this->file('file');
            if ($file && ! $v->errors()->has('file')) {
                $limits = config('whatsapp_chat.limits');
                if (! $file->isValid() || $file->getSize() === 0) {
                    $v->errors()->add('file', __('whatsapp.chat.errors.file_invalid'));
                } elseif ($file->getSize() > ($limits[$type] ?? 0)) {
                    $v->errors()->add('file', __('whatsapp.chat.errors.file_too_large', ['mb' => (int) (($limits[$type] ?? 0) / 1048576)]));
                } else {
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                    $allowed = config("whatsapp_chat.mimes.$type"); // null = any (document)
                    if ($allowed !== null && ! in_array($mime, $allowed, true)) {
                        $v->errors()->add('file', __('whatsapp.chat.errors.file_type'));
                    } elseif (! app(MediaScanner::class)->isClean($file->getRealPath())) {
                        $v->errors()->add('file', __('whatsapp.chat.errors.file_infected'));
                    }
                }
            }
            $count = count((array) $this->input('poll.options', []));
            if ($type === 'poll' && (int) $this->input('poll.selectable_count', 1) > $count) {
                $v->errors()->add('poll.selectable_count', __('whatsapp.chat.errors.poll_selectable'));
            }
        }];
    }

    public function attributes(): array
    {
        return trans('whatsapp.chat.fields');
    }
}
