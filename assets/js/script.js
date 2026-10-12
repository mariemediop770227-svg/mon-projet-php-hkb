// ============================================================
// SCRIPTS GLOBAUX - PONT HKB
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // -- Auto-fermeture des alertes après 5 secondes --
    const alertes = document.querySelectorAll('.alert.alert-success');
    alertes.forEach(function (alerte) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alerte);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // -- Confirmation suppression --
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const message = this.dataset.confirm
                || 'Êtes-vous sûr de vouloir effectuer cette action ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // -- Majuscules automatiques plaques --
    document.querySelectorAll('.plaque-input').forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    });

    // -- Tooltip Bootstrap --
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // -- Highlight ligne tableau au clic --
    document.querySelectorAll('.table tbody tr').forEach(function (tr) {
        tr.addEventListener('click', function () {
            document.querySelectorAll('.table tbody tr')
                .forEach(r => r.classList.remove('table-active'));
            this.classList.add('table-active');
        });
    });

    // -- Recherche dans les tableaux --
    const recherche = document.getElementById('rechercheTableau');
    if (recherche) {
        recherche.addEventListener('keyup', function () {
            const terme = this.value.toLowerCase();
            const lignes = document.querySelectorAll('.table tbody tr');
            lignes.forEach(function (ligne) {
                const texte = ligne.textContent.toLowerCase();
                ligne.style.display = texte.includes(terme) ? '' : 'none';
            });
        });
    }

    // -- Horloge en temps réel --
    const horloge = document.getElementById('horloge');
    if (horloge) {
        function mettreAJourHorloge() {
            const now  = new Date();
            const h    = String(now.getHours()).padStart(2, '0');
            const m    = String(now.getMinutes()).padStart(2, '0');
            const s    = String(now.getSeconds()).padStart(2, '0');
            horloge.textContent = h + ':' + m + ':' + s;
        }
        mettreAJourHorloge();
        setInterval(mettreAJourHorloge, 1000);
    }

    // -- Compteur de caractères --
    document.querySelectorAll('[data-maxlength]').forEach(function (el) {
        const max      = parseInt(el.dataset.maxlength);
        const compteur = document.createElement('small');
        compteur.className = 'text-muted float-end';
        el.parentNode.insertBefore(compteur, el.nextSibling);

        function mettreAJour() {
            const restant = max - el.value.length;
            compteur.textContent = restant + ' caractères restants';
            compteur.className   = 'text-' + (restant < 20 ? 'danger' : 'muted')
                                 + ' float-end small';
        }
        el.addEventListener('input', mettreAJour);
        mettreAJour();
    });

    // -- Imprimer page --
    document.querySelectorAll('.btn-imprimer').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    });

    // -- Confirmation déconnexion --
    document.querySelectorAll('a[href*="logout"]').forEach(function (lien) {
        lien.addEventListener('click', function (e) {
            if (!confirm('Voulez-vous vraiment vous déconnecter ?')) {
                e.preventDefault();
            }
        });
    });

});

// -- Fonction globale : afficher/masquer mot de passe --
function toggleMotDePasse(inputId, iconeId) {
    const input = document.getElementById(inputId);
    const icone = document.getElementById(iconeId);
    if (!input) return;
    if (input.type === 'password') {
        input.type       = 'text';
        if (icone) icone.className = 'bi bi-eye-slash';
    } else {
        input.type       = 'password';
        if (icone) icone.className = 'bi bi-eye';
    }
}

// -- Fonction globale : copier dans le presse-papier --
function copierTexte(texte) {
    navigator.clipboard.writeText(texte).then(function () {
        alert('✅ Copié : ' + texte);
    }).catch(function () {
        alert('❌ Impossible de copier.');
    });
}

// -- Fonction globale : formater montant --
function formatMontant(montant) {
    return new Intl.NumberFormat('fr-FR').format(montant) + ' FCFA';
}