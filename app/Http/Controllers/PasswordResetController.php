<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
final class PasswordResetController extends Controller {
    public function requestForm(): View { return view('auth.forgot-password'); }
    public function sendLink(Request $request): RedirectResponse {
        $request->validate(['email'=>['required','email']]);
        $status=Password::sendResetLink($request->only('email'));
        return $status===Password::RESET_LINK_SENT ? back()->with('status',__($status)) : back()->withErrors(['email'=>__($status)]);
    }
    public function resetForm(Request $request,string $token): View { return view('auth.reset-password',['token'=>$token,'email'=>$request->string('email')->toString()]); }
    public function reset(Request $request): RedirectResponse {
        $request->validate(['token'=>['required'],'email'=>['required','email'],'password'=>['required','confirmed',PasswordRule::min(12)->mixedCase()->numbers()->symbols()]]);
        $status=Password::reset($request->only('email','password','password_confirmation','token'),function(User $user,string $password):void{$user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();event(new PasswordReset($user));});
        return $status===Password::PASSWORD_RESET ? redirect()->route('login')->with('status',__($status)) : back()->withErrors(['email'=>__($status)]);
    }
}
