<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use App\User;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    // public function __construct(){
    //    // OTORISASI GATE

    //   $this->middleware(function($request, $next){

    //     if(Gate::allows('manage-users')) return $next($request);

    //     abort(403, 'Anda tidak memiliki cukup hak akses');
    //   }, ['except' => ['login', 'register', 'profile']]);
    // }

    public function index(Request $request)
    {
       $users = \App\User::paginate(10);
       $filterKeyword = $request->get('keyword');
       $status = $request->get('status');

       if($status){
         $users = \App\User::where('status', $status)->paginate(10);
       } else {
         $users = \App\User::paginate(10);
       }

       if($filterKeyword){
         if($status){
             $users = \App\User::where('email', 'LIKE', "%$filterKeyword%")
                 ->where('status', $status)
                 ->paginate(10);
         } else {
             $users = \App\User::where('email', 'LIKE', "%$filterKeyword%")
                     ->paginate(10);
         }
       }

       return view('users.index', ['users' => $users]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        \Validator::make($request->all(),[
          "name" => "required|min:5|max:100",
          "username" => "required|min:5|max:20",
          "roles" => "required",
          "phone" => "required|digits_between:10,12",
          "address" => "required|min:5|max:200",
          "email" => "required|email",
          "password" => "required",
          "password_confirmation" => "required|same:password"
        ])->validate();

        $new_user = new \App\User;

        $new_user->name = $request->get('name');
        $new_user->username = $request->get('username');
        $new_user->roles = json_encode($request->get('roles'));
        $new_user->address = $request->get('address');
        $new_user->phone = $request->get('phone');
        $new_user->email = $request->get('email');
        $new_user->password = \Hash::make($request->get('password'));

        if($request->file('avatar')){
            $file = $request->file('avatar')->store('avatars', 'public');

            $new_user->avatar = $file;
        }

        $new_user->save();

        return redirect()->route('users.create')->with('status', 'User successfully created.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
      $user = \App\User::findOrFail($id);

      return view('users.show', ['user' => $user]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
      $user = \App\User::findOrFail($id);

      return view('users.edit', ['user' => $user]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
      \Validator::make($request->all(), [
        "name" => "required|min:5|max:100",
        "roles" => "required",
        "phone" => "required|digits_between:10,12",
        "address" => "required|min:5|max:200",
      ])->validate();

      $user = \App\User::findOrFail($id);

      $user->name = $request->get('name');
      $user->roles = json_encode($request->get('roles'));
      $user->address = $request->get('address');
      $user->phone = $request->get('phone');
      $user->status = $request->get('status');

      if($request->file('avatar')){
          if($user->avatar && file_exists(storage_path('app/public/' . $user->avatar))){
              \Storage::delete('public/'.$user->avatar);
          }
          $file = $request->file('avatar')->store('avatars', 'public');
          $user->avatar = $file;
      }

      $user->save();

      return redirect()->route('users.edit', [$id])->with('status', 'User succesfully updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
      $user = \App\User::findOrFail($id);

      $user->delete();

      return redirect()->route('users.index')->with('status', 'User successfully delete');
    }

    public function login(Request $request)
    {
      $this->validate($request, [
          'email' => 'required',
          'password' => 'required',
      ]);

      $user = User::where('email', '=', $request->email)->where('roles', '["CUSTOMER"]')->first();
      $status = "error";
      $message = "";
      $data = null;
      $code = 302;

      if($user){
        if(Hash::check($request->password, $user->password)){
          $status = 'success';
          $message = 'Login sukses';
          $user->token = $request->token;
          $user->save();
          // tampilkan data user menggunakan method toArray
          $data = $user->toArray();
          $code = 200;
        }else{
          $message = "Login gagal, password salah";
        }
      }else {
        $message = "Login gagal, user " . $request->email . " tidak ditemukan";
      }

      return response()->json([
          'status' => $status,
          'message' => $message,
          'data' => $data
      ], $code);
    }

    public function register(Request $request)
    {
      $validator = Validator::make($request->all(), [
          'name' => 'required|string|max:255|min:5',
          'email' => 'required|string|email|max:255|unique:users',
          'username' => 'required|string|min:5|max:255|unique:users',
          'password' => 'required|string|min:6',
      ]);

      $status = "error";
      $message = "";
      $data = null;
      $code = 400;
      if ($validator->fails()) {
          $errors = $validator->errors();
          $message = $errors;
      }
      else{
          $user = \App\User::create([
              'name' => $request->name,  
              'email' => $request->email,
              'username' => $request->username,
              'phone' => $request->phone,
              'address' => $request->address,
              'token' => $request->token,
              'password' => Hash::make($request->password),
              'roles'    => json_encode(['CUSTOMER']),
          ]);
          if($user){
              // Auth::login($user);
              // $user->generateToken();
              $status = "success";
              $message = "register successfully";
              $data = $user->toArray();
              $code = 200;
          }
          else{
              $message = 'register failed';
          }
      }

      return response()->json([
          'status' => $status,
          'message' => $message,
          'data' => $data
      ], $code);
    }

    public function profile(Request $request)
    {
      $user = \App\User::findOrFail($request->id);

      $data = $user->toArray();

      return response()->json([
          'data' => $data
      ]);
    }
    
}
