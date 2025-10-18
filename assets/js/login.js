// Toggle between Login and Sign Up forms
const loginForm = document.getElementById('login');
const signUpForm = document.getElementById('Sign-Up');
const btn = document.getElementById('btn');
const loginBtn = document.querySelectorAll('.toggle-btn')[0];
const signUpBtn = document.querySelectorAll('.toggle-btn')[1];

function Login() {
    loginForm.style.left = '50px';
    signUpForm.style.left = '450px';
    btn.style.left = '0px';
}

function SignUp() {
    loginForm.style.left = '-400px';
    signUpForm.style.left = '50px';
    btn.style.left = '110px';
}

// Set initial state
window.onload = function() {
    Login();
};

// Expose functions globally
window.Login = Login;
window.SignUp = SignUp;
