<!DOCTYPE html>
<html lang="es">

@include('backend.urlglobal')

<head>
    <title>Obra de Banco</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/login/bootstrap.min.css') }}">

    <!-- icono del sistema -->
    <link rel="icon" type="image/png"  href="{{ asset('images/logosistema.png') }}">

    <!-- libreria -->
    <link href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}" type="text/css" rel="stylesheet" />

    <!-- estilo de toast -->
    <link href="{{ asset('css/toastr.min.css') }}" rel="stylesheet">
    <!-- estilo de sweet -->
    <link href="{{ asset('css/sweetalert2.min.css') }}" rel="stylesheet">

    <style>
        html, body {
            height: 100%;
        }
        body {
            font-family: 'Roboto', sans-serif;
            background-image: url({{ asset('images/fondo3.jpg') }});
            background-size: cover;
            background-repeat: no-repeat;
        }

        .demo-container {
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .btn-lg {
            padding: 12px 26px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: 999px; /* botón redondeado tipo pastilla */
        }

        ::placeholder {
            font-size:14px;
            letter-spacing:0.5px;
        }

        .form-control-lg {
            font-size: 16px;
            padding: 25px 20px;
        }

        .font-500{
            font-weight:500;
        }

        /* Card de login */
        .login-card{
            padding: 50px 55px;
            border-radius: 12px;
        }

        .login-logo{
            width:170px;
            margin-bottom: 15px;
        }

        .login-title{
            font-weight:700;
            letter-spacing:1px;
            margin-bottom:30px;
        }

        .login-form label{
            text-align: left !important;
            display: block;
        }

        .btn-register-gray {
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 12px 26px;
            background-color: #6c757d;
            color: #fff;
            transition: all 0.25s ease;
        }

        .btn-register-gray:hover {
            background-color: #6c757d; /* gris bootstrap */
            color: #fff;
            box-shadow: 0 8px 18px rgba(108,117,125,.25);
            transform: translateY(-1px);
        }

    </style>
</head>

<body>
<div class="container">
    <div class="demo-container" style="margin-top: 10px">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-12 mx-auto">

                    <div class="bg-white shadow-lg text-center login-card">

                        <!-- LOGO + TEXTO COMO EN LA CAPTURA -->
                        <img src="{{ asset('images/logo.png') }}" class="login-logo" alt="Santa Ana Norte">


                        <!-- TÍTULO  -->
                        <h4 class="login-title">Taller Estructuras Metálicas</h4>
                        <form class="login-form">
                            <div>
                                <label class="font-500">Usuario</label>
                                <input class="form-control form-control-lg mb-3"
                                       id="usuario" maxlength="100" autocomplete="off" type="text">

                                <label class="font-500">Contraseña</label>
                                <input class="form-control form-control-lg"
                                       id="password" maxlength="100" type="password">

                                <input type="button"
                                       value="INICIAR SESIÓN"
                                       style="margin-top: 20px"
                                       onclick="login()"
                                       class="btn btn-primary btn-lg w-100 shadow-lg">



                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/jquery.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('js/sweetalert2.all.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('js/alertaPersonalizada.js') }}"></script>


<script type="text/javascript">

    // onkey Enter
    var input = document.getElementById("password");
    input.addEventListener("keyup", function(event) {
        if (event.keyCode === 13) {
            event.preventDefault();
            login();
        }
    });

    // inicio de sesion
    function login() {

        var usuario = document.getElementById('usuario').value;
        var password = document.getElementById('password').value;

        if(usuario === ''){
            toastr.error('usuario es requerido');
            return;
        }

        if(password === ''){
            toastr.error('contraseña es requerida');
            return;
        }

        openLoading();

        let formData = new FormData();
        formData.append('usuario', usuario);
        formData.append('password', password);

        axios.post(urlAdmin+'/login', formData, {
        })
            .then((response) => {
                closeLoading();
                verificar(response);
            })
            .catch((error) => {
                toastr.error('error al iniciar sesión');
                closeLoading();
            });
    }

    // estados de la verificacion
    function verificar(response) {

        if (response.data.success === 0) {
            toastr.error('validación incorrecta')
        } else if (response.data.success === 1) {
            window.location = response.data.ruta;
        } else if (response.data.success === 2) {
            toastr.error('Datos incorrectos');
        } else if (response.data.success === 3) {
            toastr.error('usuario no encontrado')
        } else {
            toastr.error('error al iniciar sesión');
        }
    }


</script>
</body>

</html>
