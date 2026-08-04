<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home');
    }

    public function privacy(): View
    {
        return view('site.privacy');
    }

    public function terms(): View
    {
        return view('site.terms');
    }

    public function contact(ContactRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Mail::to(config('site.contact_mail_address'))->send(new ContactMessage(
            senderName: $data['name'],
            senderEmail: $data['email'],
            body: $data['message'],
        ));

        return redirect()
            ->to(route('site.home').'#contact')
            ->with('contact-sent', true);
    }
}
