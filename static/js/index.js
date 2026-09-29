var template;

init.push(function(){
  Handlebars.registerHelper('formatNumber', function (number, digits) {
    if(typeof number === 'undefined'){
      return 0;
    }

    if (Number.isInteger(digits)) {
      return number.toLocaleString('en-US', {minimumFractionDigits: digits, maximumFractionDigits: digits});
    }
    return number.toLocaleString('en-US');
  });

  Handlebars.registerHelper('formatNano', function (number) {
    if(typeof number === 'undefined'){
      return 0;
    }

    return number.toLocaleString('en-US', {minimumFractionDigits: GLOBAL_DIGITS, maximumFractionDigits: GLOBAL_DIGITS});
  });

  Handlebars.registerHelper('formatSeconds', function (number) {
    if(typeof number === 'undefined'){
      return 0;
    }

    var hours   = Math.floor(number / 3600);
    var minutes = Math.floor((number - (hours * 3600)) / 60);
    var seconds = number - (hours * 3600) - (minutes * 60);

    if (hours   < 10) {hours   = "0"+hours;}
    if (minutes < 10) {minutes = "0"+minutes;}
    if (seconds < 10) {seconds = "0"+seconds;}
    return hours+'h '+minutes+'m '+seconds+'s';
  });

  Handlebars.registerHelper('ifCond', function (v1, operator, v2, options) {

    switch (operator) {
        case '==':
            return (v1 == v2) ? options.fn(this) : options.inverse(this);
        case '===':
            return (v1 === v2) ? options.fn(this) : options.inverse(this);
        case '!=':
            return (v1 != v2) ? options.fn(this) : options.inverse(this);
        case '!==':
            return (v1 !== v2) ? options.fn(this) : options.inverse(this);
        case '<':
            return (v1 < v2) ? options.fn(this) : options.inverse(this);
        case '<=':
            return (v1 <= v2) ? options.fn(this) : options.inverse(this);
        case '>':
            return (v1 > v2) ? options.fn(this) : options.inverse(this);
        case '>=':
            return (v1 >= v2) ? options.fn(this) : options.inverse(this);
        case '&&':
            return (v1 && v2) ? options.fn(this) : options.inverse(this);
        case '||':
            return (v1 || v2) ? options.fn(this) : options.inverse(this);
        default:
            return options.inverse(this);
    }
});

  axios.get('templates/index.hbs')
  .then(function (response) {
    template=Handlebars.compile(response.data);

    updateStats();
  });
});

function updateStats(){
  axios.get('api.php')
  .then(function (response) {
    document.getElementById("content").innerHTML = template(response.data);
    new ClipboardJS('#copyAccount');
  })
  .catch(function (error) {
    console.log('FAIL', error);
    if(error.response){
      showError(error.response.data);
    }
  })
  .finally(function () {
    setTimeout(updateStats, GLOBAL_REFRESH * 1000);
  });
}

// Render an API error as plain text in the .myError box. The response body is
// never inserted as markup: only its text is extracted (myError() sends
// '<div class="myError">message</div>'; proxies/CDNs may send full pages).
function showError(data){
  var text = '';
  if (typeof data === 'string') {
    try {
      text = new DOMParser().parseFromString(data, 'text/html').body.textContent;
    } catch (e) {
      text = '';
    }
  }
  var box = document.createElement('div');
  box.className = 'myError';
  box.textContent = (text && text.trim()) ? text : 'API error';
  var content = document.getElementById('content');
  content.textContent = '';
  content.appendChild(box);
}
