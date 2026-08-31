<?php

namespace App\Http\Controllers;

use App\Models\SalaryDeductionForm;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the signed Salary Deduction Form a contractor uploaded.
 *
 * Two ways out: inline, so the reviewer can read it in a browser tab without
 * saving anything, and as an attachment when they want a copy. Livewire turns
 * every file response it handles into a download, so viewing has to go through
 * a real route rather than a component action.
 */
class SalaryDeductionFormController extends Controller
{
    /**
     * Open the form in the browser.
     *
     * The optional trailing {filename} segment is ignored here; it only gives
     * the browser tab a sensible title, matching DocumentController::show().
     */
    public function show(SalaryDeductionForm $form, ?string $filename = null)
    {
        $this->authorizeAccess($form);

        return $this->respond($form, 'inline');
    }

    /**
     * Save the form to disk.
     */
    public function download(SalaryDeductionForm $form)
    {
        $this->authorizeAccess($form);

        return $this->respond($form, 'attachment');
    }

    /**
     * Admins review every contractor's forms; a contractor sees only its own.
     */
    protected function authorizeAccess(SalaryDeductionForm $form): void
    {
        $user = auth()->user();

        if ($user->hasAdminAccess()) {
            return;
        }

        abort_unless($user->contractor_clab_no === $form->contractor_clab_no, 403);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function respond(SalaryDeductionForm $form, string $disposition)
    {
        if (! Storage::disk('local')->exists($form->file_path)) {
            \Log::warning('Salary deduction form missing from storage', [
                'form_id' => $form->id,
                'contractor_clab_no' => $form->contractor_clab_no,
                'file_path' => $form->file_path,
            ]);

            abort(404, 'The signed form is missing from storage. Please contact the administrator.');
        }

        return Storage::disk('local')->response(
            $form->file_path,
            $form->file_name,
            [
                'Content-Type' => $form->mime_type ?: 'application/pdf',
                'Content-Disposition' => $disposition.'; filename="'.addslashes($form->file_name).'"',
            ]
        );
    }
}
