
var domains = [];
$( document ).ready(function() {
    var email = window.location.hash.substr(1);
    if(validateEmail(email))
        loadAccount(email)

    $.get("api.php?a=getdoms",function(data){
        console.log(data)
        domains = data;
    },"json")
});

function loadMail(email,id)
{
    $.get("api.php?a=load&email="+email+"&id="+id,function(data){
        //console.log(data);
        if(data.status=="ok")
        {
            renderEmail(email,id,data.data)
        }
    },"json")
}

function renderEmail(email,id,data)
{
    console.log("rendering")
    console.log(data)
    var btns = ''
    for(att in data.parsed.attachments)
    {
        console.log(data.parsed.attachments[att])
        var filename=data.parsed.attachments[att].substr(14)
        btns+='<a class="btn btn-primary" target="_blank" href="api.php?a=attachment&email='+email+'&id='+id+'&filename='+filename+'" role="button">'+filename+'</a>'
    }
    $("#main").html('<h2 class="text-center">'+email+'</h2>\
        '+(data.parsed.body?'<pre>'+data.parsed.body+'</pre>':'')+' \
        '+(data.parsed.htmlbody?'<div class="card card-body bg-light">'+data.parsed.htmlbody+'</pre>':'')+' \
        '+(btns!==''?'<h4>Attachments</h4>'+btns:'')+'\
        ')
}

function loadAccount(email)
{
    if(validateEmail(email))
    {
        var index = 1;
        changeHash(email)
        $("#main").html('<h2 class="text-center">'+email+'</h2>\
        <table class="table table-hover">\
            <thead>\
                <tr>\
                    <th scope="col">#</th>\
                    <th scope="col">Date</th>\
                    <th scope="col">From</th>\
                    <th scope="col">Subject</th>\
                </tr>\
            </thead>\
            <tbody id="emailtable">\
            </tbody>\
            </table>\
        ')

        $.get("api.php?a=list&email="+email,function(data){
            console.log(data);
            if(data.status=="ok")
            {
                if(Object.keys(data.emails).length>0)
                    for(em in data.emails)
                    {
                        var date = new Date(parseInt(em))
                        var datestring = date.getDate()+"."+date.getMonth()+"."+date.getFullYear()+" "+date.getHours()+":"+date.getMinutes();
                        var ed = data.emails[em]
                        $("#emailtable").append('\
                            <tr class="anemail" onClick="loadMail(\''+email+'\','+em+');">\
                                <th scope="row">'+(index++)+'</th>\
                                <td >'+datestring+'</td>\
                                <td>'+ed.from.toHtmlEntities()+'</td>\
                                <td>'+ed.subject.toHtmlEntities()+'</td>\
                            </tr>');
                    }
                else{
                    console.log("leider keine post")
                    $("#emailtable").append('\
                        <tr>\
                            <td colspan="4" class="text-center" ><h4>No emails received on this address (yet..)</h4></td>\
                        </tr>');
                }
            }
        },"json")
    }
    else
    {
        changeHash("")
    }
}

function accessAccount()
{
    var email = $("#email").val()
    if(email)
        loadAccount(email)
    else alert("Enter an email you want to access")
}

function generateAccount()
{
    var email = makeName()+'@'+domains[Math.floor(Math.random()*domains.length)];
    
    loadAccount(email)
}

function changeHash(val)
{
    if(history.pushState) {
        history.pushState(null, null, '#'+val);
    }
    else {
        location.hash = '#'+val;
    }
}

function validateEmail(email) {
    var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Convert a string to HTML entities
 */
String.prototype.toHtmlEntities = function() {
    return this.replace(/./gm, function(s) {
        return "&#" + s.charCodeAt(0) + ";";
    });
};

/**
 * Create string from HTML entities
 */
String.fromHtmlEntities = function(string) {
    return (string+"").replace(/&#\d+;/gm,function(s) {
        return String.fromCharCode(s.match(/\d+/gm)[0]);
    })
};