/* PixelStore - comportamentos de interface.
   Toda regra de negócio (estoque, preços, permissões) é validada no servidor;
   este arquivo só melhora a experiência de uso. */

document.addEventListener('DOMContentLoaded', function () {

    // Botões + e - do seletor de quantidade, respeitando min/max (estoque)
    document.querySelectorAll('.seletor-qtd').forEach(function (grupo) {
        var campo = grupo.querySelector('input[type="number"]');
        if (!campo) return;

        grupo.querySelectorAll('[data-qtd]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var min = parseInt(campo.min, 10) || 1;
                var max = parseInt(campo.max, 10) || Infinity;
                var atual = parseInt(campo.value, 10) || min;
                var novo = atual + parseInt(botao.dataset.qtd, 10);
                campo.value = Math.min(max, Math.max(min, novo));
            });
        });
    });

    // Impede digitar quantidade fora do intervalo permitido
    document.querySelectorAll('input[type="number"][max]').forEach(function (campo) {
        campo.addEventListener('change', function () {
            var min = parseInt(campo.min, 10);
            var max = parseInt(campo.max, 10);
            var valor = parseInt(campo.value, 10);

            if (isNaN(valor)) valor = isNaN(min) ? 1 : min;
            if (!isNaN(max) && valor > max) valor = max;
            if (!isNaN(min) && valor < min) valor = min;
            campo.value = valor;
        });
    });

    // Confirmação antes de ações destrutivas
    document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            if (!window.confirm(form.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });

    // Selects que enviam o formulário ao mudar (ordenação da vitrine)
    document.querySelectorAll('select[data-auto-submit]').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    // Validação visual do Bootstrap + conferência de senha
    document.querySelectorAll('form.precisa-validar').forEach(function (form) {
        var confirmar = form.querySelector('[data-igual-a]');

        function conferirSenha() {
            if (!confirmar) return;
            var original = form.querySelector('#' + confirmar.dataset.igualA);
            confirmar.setCustomValidity(original && confirmar.value !== original.value ? 'diferente' : '');
        }

        if (confirmar) {
            form.addEventListener('input', conferirSenha);
        }

        form.addEventListener('submit', function (evento) {
            conferirSenha();
            if (!form.checkValidity()) {
                evento.preventDefault();
                evento.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Checkout: mostra a explicação da forma de pagamento selecionada
    var formCheckout = document.getElementById('form-checkout');
    if (formCheckout) {
        var detalhes = formCheckout.querySelectorAll('.detalhe-pagamento');

        function mostrarDetalhe() {
            var marcado = formCheckout.querySelector('input[name="forma_pagamento"]:checked');
            detalhes.forEach(function (bloco) {
                bloco.classList.toggle('d-none', !marcado || bloco.dataset.forma !== marcado.value);
            });
        }

        formCheckout.querySelectorAll('input[name="forma_pagamento"]').forEach(function (radio) {
            radio.addEventListener('change', mostrarDetalhe);
        });
        mostrarDetalhe();

        // Evita envio duplicado do pedido
        formCheckout.addEventListener('submit', function () {
            var botao = document.getElementById('btn-confirmar');
            if (botao) {
                botao.disabled = true;
                botao.textContent = 'Processando pagamento...';
            }
        });
    }
});
