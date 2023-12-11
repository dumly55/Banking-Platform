const validator = new JustValidate('#register');

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
    .addField('#username', [
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
    .addField('#pin', [
        {
            rule:"required"
        }
    ])
    .addField('#cpin', [
        {
            validator: (value, fields) => {
                return value === fields['#pin'].elem.value;
            },
            errorMessage: 'Pins should match'
        }
    ])
    .addField('#email', [
        {
            rule:"required"
        },
        {
            rule:"email"
        }
    ])
    .addField('#ssn', [
        {
            rule:"required"
        }
    ])
    .addField('#phone', [
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
    .onSuccess((event) => {
        document.getElementById("register").submit();
    });
    
