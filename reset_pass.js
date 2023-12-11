const validator = new JustValidate('#forgot-form');

validator
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
    .onSuccess((event) => {
        document.getElementById("forgot-form").submit();
    });
    
