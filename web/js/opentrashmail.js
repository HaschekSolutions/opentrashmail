
var domains = [];
var lastid = 0;
var activeemail = '';
var timer;
$( document ).ready(function() {

    $.ajaxSetup({ cache: false });

    var email = window.location.hash.substr(1);
    if(validateEmail(email))
        loadAccount(email)

    $.get("api.php?a=getdoms",function(data){
        domains = data;
    },"json")
});

function loadMail(email,id)
{
    $.get("api.php?a=load&email="+email+"&id="+id,function(data){
        //
        if(data.status=="ok")
        {
            renderEmail(email,id,data.data)
        }
    },"json")
}

function renderEmail(email,id,data)
{
    clearInterval(timer);
    var btns = ''
    for(att in data.parsed.attachments)
    {
        var filename=data.parsed.attachments[att].substr(14)
        btns+='<a class="btn btn-primary" target="_blank" href="api.php?a=attachment&email='+email+'&id='+id+'&filename='+filename+'" role="button">'+filename+'</a>'
    }
    $("#main").html('<h2 class="text-center">'+email+'</h2>\
        <button onClick="loadAccount(\''+activeemail+'\')" class="btn btn-primary my-2 my-sm-0"><i class="fas fa-backward"></i> Back</button>\
        '+(data.parsed.body?'<pre>'+data.parsed.body+'</pre>':'')+' \
        '+(data.parsed.htmlbody?'<div class="card card-body bg-light">'+data.parsed.htmlbody+'</pre>':'')+' \
        '+(btns!==''?'<h4>Attachments</h4>'+btns:'')+'\
        ')
}

function loadAccount(email)
{
    if(validateEmail(email))
    {
        activeemail = email;
        
        lastid = 0;
        changeHash(email)
        $("#main").html('<h2 class="text-center">'+email+'</h2>\
        <button onClick="loadAccount(\''+email+'\')" class="btn btn-success my-2 my-sm-0"><i class="fas fa-sync-alt"></i> Refresh</button>\
        <table class="table table-hover">\
            <thead>\
                <tr id="tableheader">\
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

        timer = setInterval(updateEmailTable, 5000); //check for new mail every 5 seconds
        updateEmailTable(); //and check now
    }
    else
    {
        changeHash("")
    }
}

function updateEmailTable()
{
    var email = activeemail;
    var index = 1;
    console.log("Checking mail for "+email)

    $.get("api.php?a=list&email="+email+"&lastid="+lastid,function(data){
        if(data.status=="ok")
        {
            var admin=false;
            if(data.type=="admin")
            {
                clearInterval(timer);
                admin = true;
                $('#tableheader').children(':eq(1)').after('<th scope="col">To</th>');
            }

            if(Object.keys(data.emails).length>0)
                for(em in data.emails)
                {
                    if($("#nomailyet").length != 0)
                        $("#nomailyet").remove();
                    if(admin===true)
                    {
                        dateofemail=em.split("-")[0];
                        email = em.substring(em.indexOf('-') + 1);
                    }
                    else dateofemail = em;
                    if(em>lastid) lastid = em;

                    //var date = new Date(parseInt(dateofemail))
                    //var datestring = date.getDate()+"."+date.getMonth()+"."+date.getFullYear()+" "+date.getHours()+":"+date.getMinutes();
					var datestring = moment.unix(parseInt(dateofemail/1000)).format(data.dateformat); // Use moment.js formatting
                    var ed = data.emails[em]
                    $("#emailtable").append('\
                        <tr class="anemail" onClick="loadMail(\''+email+'\','+dateofemail+');">\
                            <th scope="row">'+(index++)+'</th>\
                            <td >'+datestring+'</td>\
                            '+(admin===true?'<td>'+email+'</td>':'')+'\
                            <td>'+ed.from.toHtmlEntities()+'</td>\
                            <td>'+ed.subject.toHtmlEntities()+'</td>\
                        </tr>');
                }
            else if(lastid==0 && $("#nomailyet").length == 0){
                $("#emailtable").append('\
                    <tr id="nomailyet">\
                        <td  colspan="4" class="text-center" ><h4>No emails received on this address (yet..)</h4></td>\
                    </tr>');
            }
        }
    },"json")
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
    if(domains===null)
        alert("No domains configured in settings.ini")
    else
    {
        var email = makeName()+'@'+domains[Math.floor(Math.random()*domains.length)];
        loadAccount(email)
    }
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