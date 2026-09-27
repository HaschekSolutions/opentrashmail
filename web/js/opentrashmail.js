function navbarmanager() {
    var x = document.getElementById("OTMTopnav");
    if (x.className === "topnav") {
      x.className += " responsive";
    } else {
      x.className = "topnav";
    }
}
// Address typed in the top bar. Without an @ the domain from the dropdown is added
function otmFullEmail() {
    var email = document.getElementById('email').value.trim();
    var domain = document.getElementById('emaildomain');
    if (email !== '' && email.indexOf('@') === -1 && domain)
        email += '@' + domain.value;
    return email;
}
