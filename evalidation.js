const validator = new JustValidate('#form-login');

validator
    .addField('#first', [
        {
            rule:"required"
        }
    ])
    .addField('#last', [
        {
            rule:"required"
        }
    ])
    .addField('#password', [
        {
            rule:"required"
        },
        {
            rule:"password"
        }
    ])
    .addField('#cpassword', [
        {
            validator: (value, fields) => {
                return value === fields['#password'].elem.value;
            },
            errorMessage: 'Passwords should match'
        }
    ])
    .addField('#phone', [
        {
            rule:"required"
        }
    ])
    .addField('#address', [
        {
            rule:"required"
        }
    ])
    .addField('#city', [
        {
            rule:"required"
        }
    ])
    .addField('#state', [
        {
            rule:"required"
        }
    ])
    .addField('#zip', [
        {
            rule:"required"
        }
    ])
    .addField('#lstate', [
        {
            rule:"required"
        }
    ])
    .addField('#license', [
        {
            rule:"required"
        }
    ])
    .addField('#ssn', [
        {
            rule:"required"
        }
    ])
    .onSuccess((event) => {
        document.getElementById("form-login").submit();
    });
    
