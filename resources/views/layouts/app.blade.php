<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="csrf-token" content="{{ csrf_token() }}">
        <!-- Fonts -->
        <!-- <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet" type="text/css"> -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!-- Styles -->
        <link rel="stylesheet" type="text/css" href="/css/app.css">

        <title>{{ config('app.name', 'Laravel') }} | @yield('title')</title>
    </head>
    <body>
        <div id="app">
            <nav class="navbar navbar-expand-md navbar-dark bg-dark">
                <div class="container">
                    <a class="navbar-brand" href="{{ url('/') }}">
                        {{ config('app.name', 'Laravel') }}
                    </a>
                    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <!-- Left Side Of Navbar -->
                        <ul class="navbar-nav mr-auto">
                            <li class="nav-item {{ Request::routeIs('streams.index') ? 'active' : null }}">
                                <a class="nav-link" href="{{ route('streams.index') }}">{{ __('Watch') }}</a>
                            </li>
                            <li class="nav-item {{ Request::routeIs('streams.record') ? 'active' : null }}">
                                <a class="nav-link" href="{{ route('streams.record') }}">{{ __('Record') }}</a>
                            </li>
                            <li class="nav-item {{ Request::routeIs('users.index') ? 'active' : null }}">
                                <a class="nav-link" href="{{ route('users.index') }}">{{ __('Streamers') }}</a>
                            </li>
                            @yield('navigation')
                        </ul>

                        @yield('search')

                        <!-- Right Side Of Navbar -->
                        <ul class="navbar-nav ml-auto">
                            <!-- Authentication Links -->
                            @guest
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                                <li class="nav-item">
                                    @if (Route::has('register'))
                                        <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                    @endif
                                </li>
                            @else
                                <li class="nav-item dropdown active">
                                    <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                        {{ Auth::user()->name }} <span class="caret"></span>
                                    </a>

                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
        
                                        <a class="dropdown-item" href="{{ route('users.edit', ['user' => Auth::id()]) }}">
                                            {{ __('Settings') }}
                                        </a>

                                        <a class="dropdown-item" href="{{ route('streams.history') }}">
                                            {{ __('History') }}
                                        </a>

                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="{{ route('logout') }}"
                                        onclick="event.preventDefault();
                                                        document.getElementById('logout-form').submit();">
                                            {{ __('Logout') }}
                                        </a>
                                        
                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                            @csrf
                                        </form>
                                    </div>
                                </li>
                            @endguest
                        </ul>
                    </div>
                </div>
            </nav>

            <main class="py-4">
            @yield('content')
            </main> 
        </div>
        <script type="text/javascript" src="{{ mix('js/app.js') }}"></script>
        @stack('scripts')
    </body>    
</html>
