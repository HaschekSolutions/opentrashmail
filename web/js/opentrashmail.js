function navbarmanager() {
    var x = document.getElementById("OTMTopnav");
    if (x.className === "topnav") {
      x.className += " responsive";
    } else {
      x.className = "topnav";
    }
}