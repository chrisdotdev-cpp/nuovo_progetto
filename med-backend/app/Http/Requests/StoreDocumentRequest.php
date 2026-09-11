<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 20 MB: sufficiente per referti e imaging compresso
            'file'               => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'category'           => ['required', 'in:consenso,referto,fattura,contratto,certificazione,altro'],
            'patient_id'         => ['nullable', 'exists:patients,id'],
            'status'             => ['nullable', 'in:bozza,da_firmare,firmato,in_conservazione,archiviato'],
            'parent_document_id' => ['nullable', 'exists:documents,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max'   => 'Il file non puo\' superare i 20 MB.',
            'file.mimes' => 'Formato non consentito: usa PDF, immagini o documenti Office.',
        ];
    }
}
