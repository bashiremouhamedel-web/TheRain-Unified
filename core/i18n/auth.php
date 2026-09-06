<?php

require_once dirname(__DIR__) . '/config/catalog.php';

if (!function_exists('therain_auth_locale')) {
    function therain_auth_locale()
    {
        if (!empty($_GET['lang']) && array_key_exists($_GET['lang'], therain_language_options())) {
            $locale = $_GET['lang'];
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['therain_locale'] = $locale;
            }
            if (!headers_sent()) {
                setcookie('therain_locale', $locale, time() + 31536000, '/', '', false, true);
            }
            return $locale;
        }

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['therain_locale'])) {
            return $_SESSION['therain_locale'];
        }

        if (!empty($_COOKIE['therain_locale']) && array_key_exists($_COOKIE['therain_locale'], therain_language_options())) {
            return $_COOKIE['therain_locale'];
        }

        return 'en';
    }
}

if (!function_exists('therain_auth_translations')) {
    function therain_auth_translations($locale)
    {
        $translations = array(
            'en' => array(
                'sign_in' => 'Sign in', 'create_account' => 'Create account', 'welcome_back' => 'Welcome back',
                'sign_in_description' => 'Sign in to continue to your workspace.', 'email' => 'Email address',
                'email_or_username' => 'Email or username', 'password' => 'Password', 'remember_me' => 'Remember me',
                'forgot_password' => 'Forgot password?', 'new_account' => "New to TheRain Unified?", 'create_one' => 'Create one',
                'platform' => 'Unified Business & Management Platform', 'tagline' => 'One platform. Every management system.',
                'secure' => 'Secure, scalable, multi-tenant operations.', 'copyright' => 'All rights reserved.',
                'business' => 'Business information', 'owner' => 'Owner account', 'preferences' => 'Platform preferences',
                'business_name' => 'Business or management name', 'business_phone' => 'Business phone number',
                'business_email' => 'Business email', 'owner_name' => 'Owner full name', 'phone' => 'Phone number',
                'confirm_password' => 'Confirm password', 'currency' => 'Default currency', 'language' => 'Preferred language',
                'management_system' => 'Management system', 'profile_picture' => 'Owner profile picture',
                'business_logo' => 'Business logo', 'address' => 'Address', 'country' => 'Country', 'city' => 'City',
                'select_system' => 'Select a management system', 'coming_soon' => 'coming soon',
                'terms' => 'I agree to the platform terms and conditions.', 'create_workspace' => 'Create workspace',
                'already_account' => 'Already have an account?', 'sign_in_here' => 'Sign in here',
                'register_description' => 'Create your management workspace and become its Super Admin.',
                'password_hint' => 'At least 8 characters with letters and numbers.', 'business_type' => 'Business type',
                'business_description' => 'Business description', 'timezone' => 'Timezone', 'branding' => 'Branding',
                'upload_hint' => 'JPG, PNG, or WEBP, up to 2 MB each.', 'module_hint' => 'Only enabled management systems are available for immediate use.',
                'or' => 'OR', 'language_selector' => 'Language', 'show_password' => 'Show password', 'hide_password' => 'Hide password',
            ),
            'fr' => array(
                'sign_in' => 'Se connecter', 'create_account' => 'Creer un compte', 'welcome_back' => 'Bon retour',
                'sign_in_description' => 'Connectez-vous pour continuer vers votre espace.', 'email' => 'Adresse e-mail',
                'email_or_username' => "E-mail ou nom d'utilisateur", 'password' => 'Mot de passe', 'remember_me' => 'Se souvenir de moi',
                'forgot_password' => 'Mot de passe oublie ?', 'new_account' => 'Nouveau sur TheRain Unified ?', 'create_one' => 'Creer un compte',
                'platform' => 'Plateforme unifiee de gestion d entreprise', 'tagline' => 'Une plateforme. Chaque systeme de gestion.',
                'secure' => 'Des operations securisees, evolutives et multi-entreprises.', 'copyright' => 'Tous droits reserves.',
                'business' => 'Informations de l entreprise', 'owner' => 'Compte proprietaire', 'preferences' => 'Preferences de la plateforme',
                'business_name' => "Nom de l'entreprise ou de la gestion", 'business_phone' => "Telephone de l'entreprise",
                'business_email' => "E-mail de l'entreprise", 'owner_name' => 'Nom complet du proprietaire', 'phone' => 'Numero de telephone',
                'confirm_password' => 'Confirmer le mot de passe', 'currency' => 'Devise par defaut', 'language' => 'Langue preferee',
                'management_system' => 'Systeme de gestion', 'profile_picture' => 'Photo du proprietaire',
                'business_logo' => "Logo de l'entreprise", 'address' => 'Adresse', 'country' => 'Pays', 'city' => 'Ville',
                'select_system' => 'Selectionnez un systeme de gestion', 'coming_soon' => 'bientot disponible',
                'terms' => "J'accepte les conditions generales de la plateforme.", 'create_workspace' => 'Creer l espace',
                'already_account' => 'Vous avez deja un compte ?', 'sign_in_here' => 'Se connecter ici',
                'register_description' => 'Creez votre espace de gestion et devenez son Super Administrateur.',
                'password_hint' => 'Au moins 8 caracteres avec lettres et chiffres.', 'business_type' => "Type d'entreprise",
                'business_description' => "Description de l'entreprise", 'timezone' => 'Fuseau horaire', 'branding' => 'Identite visuelle',
                'upload_hint' => 'JPG, PNG ou WEBP, 2 Mo maximum chacun.', 'module_hint' => 'Seuls les systemes actifs sont disponibles immediatement.',
                'or' => 'OU', 'language_selector' => 'Langue', 'show_password' => 'Afficher le mot de passe', 'hide_password' => 'Masquer le mot de passe',
            ),
        );

        $fallbacks = array(
            'ar' => array('sign_in' => 'تسجيل الدخول', 'create_account' => 'إنشاء حساب', 'welcome_back' => 'مرحباً بعودتك', 'sign_in_description' => 'سجل الدخول للمتابعة إلى مساحة العمل.', 'email' => 'البريد الإلكتروني', 'email_or_username' => 'البريد الإلكتروني أو اسم المستخدم', 'password' => 'كلمة المرور', 'remember_me' => 'تذكرني', 'forgot_password' => 'هل نسيت كلمة المرور؟', 'new_account' => 'جديد على TheRain Unified؟', 'create_one' => 'أنشئ حساباً', 'platform' => 'منصة موحدة لإدارة الأعمال', 'tagline' => 'منصة واحدة. كل أنظمة الإدارة.', 'secure' => 'عمليات آمنة وقابلة للتوسع ومتعددة المستأجرين.', 'copyright' => 'جميع الحقوق محفوظة.', 'business' => 'معلومات الأعمال', 'owner' => 'حساب المالك', 'business_name' => 'اسم العمل أو الإدارة', 'business_phone' => 'هاتف العمل', 'business_email' => 'بريد العمل', 'owner_name' => 'الاسم الكامل للمالك', 'phone' => 'رقم الهاتف', 'confirm_password' => 'تأكيد كلمة المرور', 'currency' => 'العملة الافتراضية', 'language' => 'اللغة المفضلة', 'management_system' => 'نظام الإدارة', 'profile_picture' => 'صورة المالك', 'business_logo' => 'شعار العمل', 'address' => 'العنوان', 'country' => 'الدولة', 'city' => 'المدينة', 'select_system' => 'اختر نظام الإدارة', 'terms' => 'أوافق على شروط المنصة وأحكامها.', 'create_workspace' => 'إنشاء مساحة العمل', 'already_account' => 'لديك حساب بالفعل؟', 'sign_in_here' => 'سجل الدخول هنا', 'register_description' => 'أنشئ مساحة عملك وأصبح مسؤولها الرئيسي.', 'password_hint' => '8 أحرف على الأقل مع حروف وأرقام.'),
            'pt' => array('sign_in' => 'Entrar', 'create_account' => 'Criar conta', 'welcome_back' => 'Bem-vindo de volta', 'sign_in_description' => 'Entre para continuar no seu espaço de trabalho.', 'email' => 'Endereço de e-mail', 'email_or_username' => 'E-mail ou nome de utilizador', 'password' => 'Palavra-passe', 'remember_me' => 'Lembrar-me', 'forgot_password' => 'Esqueceu a palavra-passe?', 'new_account' => 'Novo no TheRain Unified?', 'create_one' => 'Criar uma conta', 'platform' => 'Plataforma unificada de gestão empresarial', 'tagline' => 'Uma plataforma. Todos os sistemas de gestão.', 'secure' => 'Operações seguras, escaláveis e multiempresa.', 'copyright' => 'Todos os direitos reservados.'),
            'sw' => array('sign_in' => 'Ingia', 'create_account' => 'Fungua akaunti', 'welcome_back' => 'Karibu tena', 'sign_in_description' => 'Ingia ili kuendelea kwenye eneo lako la kazi.', 'email' => 'Barua pepe', 'email_or_username' => 'Barua pepe au jina la mtumiaji', 'password' => 'Nenosiri', 'remember_me' => 'Nikumbuke', 'forgot_password' => 'Umesahau nenosiri?', 'new_account' => 'Mpya kwenye TheRain Unified?', 'create_one' => 'Fungua akaunti', 'platform' => 'Jukwaa la umoja la usimamizi wa biashara', 'tagline' => 'Jukwaa moja. Mifumo yote ya usimamizi.', 'secure' => 'Uendeshaji salama, unaopanuka na wa wapangaji wengi.', 'copyright' => 'Haki zote zimehifadhiwa.'),
            'ha' => array('sign_in' => 'Shiga', 'create_account' => 'Kirkiri asusu', 'welcome_back' => 'Barka da dawowa', 'sign_in_description' => 'Shiga don ci gaba da amfani da wurin aikinka.', 'email' => 'Adireshin imel', 'email_or_username' => 'Imel ko sunan mai amfani', 'password' => 'Kalmar sirri', 'remember_me' => 'Tuna ni', 'forgot_password' => 'Ka manta kalmar sirri?', 'new_account' => 'Sabo ne a TheRain Unified?', 'create_one' => 'Kirkiri asusu', 'platform' => 'Dandamalin hadadden gudanar da kasuwanci', 'tagline' => 'Dandamali daya. Duk tsarin gudanarwa.', 'secure' => 'Ayyuka masu tsaro da iya fadadawa.', 'copyright' => 'An kiyaye dukkan hakkoki.'),
            'es' => array('sign_in' => 'Iniciar sesión', 'create_account' => 'Crear cuenta', 'welcome_back' => 'Bienvenido de nuevo', 'sign_in_description' => 'Inicia sesión para continuar en tu espacio de trabajo.', 'email' => 'Correo electrónico', 'email_or_username' => 'Correo o usuario', 'password' => 'Contraseña', 'remember_me' => 'Recordarme', 'forgot_password' => '¿Olvidaste tu contraseña?', 'new_account' => '¿Nuevo en TheRain Unified?', 'create_one' => 'Crear una cuenta', 'platform' => 'Plataforma unificada de gestión empresarial', 'tagline' => 'Una plataforma. Todos los sistemas de gestión.', 'secure' => 'Operaciones seguras, escalables y multiempresa.', 'copyright' => 'Todos los derechos reservados.'),
            'zh' => array('sign_in' => '登录', 'create_account' => '创建账户', 'welcome_back' => '欢迎回来', 'sign_in_description' => '登录以继续访问您的工作区。', 'email' => '电子邮箱', 'email_or_username' => '邮箱或用户名', 'password' => '密码', 'remember_me' => '记住我', 'forgot_password' => '忘记密码？', 'new_account' => '还不是 TheRain Unified 用户？', 'create_one' => '创建账户', 'platform' => '统一的企业管理平台', 'tagline' => '一个平台。所有管理系统。', 'secure' => '安全、可扩展的多租户运营。', 'copyright' => '版权所有。'),
        );

        foreach ($fallbacks as $code => $strings) {
            $translations[$code] = array_merge($translations['en'], $strings);
        }

        return isset($translations[$locale]) ? $translations[$locale] : $translations['en'];
    }
}

if (!function_exists('therain_auth_t')) {
    function therain_auth_t($key)
    {
        static $stringsByLocale = array();
        $locale = therain_auth_locale();

        if (!isset($stringsByLocale[$locale])) {
            $stringsByLocale[$locale] = therain_auth_translations($locale);
        }

        return isset($stringsByLocale[$locale][$key]) ? $stringsByLocale[$locale][$key] : $key;
    }
}
