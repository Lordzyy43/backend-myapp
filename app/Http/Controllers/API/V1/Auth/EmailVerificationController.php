<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
  /**
   * 🔥 VERIFY EMAIL (via signed URL)
   */
  public function verify(EmailVerificationRequest $request)
  {
    if ($request->user()->hasVerifiedEmail()) {
      return $this->success(null, 'Email sudah diverifikasi');
    }

    $request->fulfill(); // 🔥 ini isi email_verified_at

    return $this->success(null, 'Email berhasil diverifikasi');
  }

  /**
   * 🔥 RESEND VERIFICATION EMAIL
   */
  public function resend(Request $request)
  {
    if ($request->user()->hasVerifiedEmail()) {
      return $this->error('Email sudah diverifikasi', null, 400);
    }

    $request->user()->sendEmailVerificationNotification();

    return $this->success(null, 'Link verifikasi berhasil dikirim ulang');
  }
}
