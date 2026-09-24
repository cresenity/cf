<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ CF::config('app.name') }} - Authorization</title>

    <!-- Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

    <style>
        .oauth-authorize .container {
            margin-top: 30px;
        }
        .oauth-authorize .scopes {
            margin-top: 20px;
        }
        .oauth-authorize .buttons {
            margin-top: 25px;
            text-align: center;
        }
        .oauth-authorize .btn {
            width: 125px;
        }
        .oauth-authorize .btn-approve {
            margin-right: 15px;
        }
        .oauth-authorize form {
            display: inline;
        }
    </style>
</head>
<body class="oauth-authorize">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card card-default">
                    <div class="card-header">
                        Login - {{ $client->name }}
                    </div>
                    <div class="card-body">
                            <!-- Login Button -->
                            <form method="post" action="{{ $loginUri }}">
                                @csrf

                                @if(c::request()->error)
                                <div class="alert alert-danger mb-3">
                                    {{ c::request()->error }}
                                </div>
                                @endif
                                <!-- Email input -->
                                <div class="form-outline mb-4">
                                    <label class="form-label" for="form2Example1">Email address</label>
                                    <input type="email" id="email" name="email" class="form-control" />
                                </div>
                                <!-- Password input -->
                                <div class="form-outline mb-4">
                                    <label class="form-label" for="form2Example2">Password</label>
                                    <input type="password" id="password" name="password"  class="form-control" />
                                </div>
                                <input type="hidden" name="state" value="{{ $request->state }}">
                                <input type="hidden" name="client_id" value="{{ $client->oauth_client_id }}">
                                <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
                                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                                @if($request->code_challenge)
                                <input type="hidden" name="code_challenge" value="{{ $request->code_challenge }}">
                                <input type="hidden" name="code_challenge_method" value="{{ $request->code_challenge_method }}">
                                @endif
                                <button type="submit" class="btn btn-success btn-signin w-100">Sign in</button>
                            </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
