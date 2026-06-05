/**
 * Scripts d'administration pour Gastro Starter
 * Gestion du téléchargement des fichiers de menu
 */

(function($) {
    'use strict';

    // Fonction pour gérer le téléversement de fichiers par glisser-déposer
    function initFileUpload() {
        if ($('#pdf-drop-area').length === 0) {
            return;
        }
        
        const dropArea = document.getElementById('pdf-drop-area');
        const fileInput = document.getElementById('file-input');
        const selectButton = document.getElementById('select-file-button');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const progressContainer = document.getElementById('upload-progress');
        const menuFileInput = document.getElementById('menu_pdf');
        
        // Prévenir le comportement par défaut pour les événements de glisser-déposer
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Mettre en surbrillance la zone de dépôt lors d'un survol
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('highlight');
        }
        
        function unhighlight() {
            dropArea.classList.remove('highlight');
        }
        
        // Gérer le dépôt de fichier
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
                uploadFile(files[0]);
            }
        }
        
        // Gérer le clic sur le bouton de sélection de fichier
        selectButton.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Gérer le changement de fichier sélectionné
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                uploadFile(fileInput.files[0]);
            }
        });
        
        // Téléverser le fichier via AJAX
        function uploadFile(file) {
            // Liste des types MIME autorisés
            const allowedTypes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ];
            
            // Vérifier que le fichier est d'un type autorisé
            if (!allowedTypes.includes(file.type)) {
                alert(gastro_starter_admin.file_type_message || 'Type de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, PPT, PPTX.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'gastro_starter_upload_menu_file');
            formData.append('security', gastro_starter_admin.nonce);
            formData.append('file', file);
            
            // Afficher la barre de progression
            progressContainer.style.display = 'block';
            
            // Créer la requête AJAX avec suivi de progression
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBar.value = percentComplete;
                    progressText.textContent = percentComplete + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        // Mettre à jour le champ avec l'URL du fichier
                        menuFileInput.value = response.data.url;
                        
                        // Ajouter un lien pour voir le fichier
                        const viewLink = document.createElement('a');
                        viewLink.href = response.data.url;
                        viewLink.target = '_blank';
                        viewLink.className = 'button button-secondary';
                        
                        // Texte du bouton adapté au type de fichier
                        const fileType = response.data.file_type || '';
                        if (fileType === 'pdf') {
                            viewLink.textContent = 'Voir le PDF';
                        } else if (['jpg', 'jpeg', 'png'].includes(fileType)) {
                            viewLink.textContent = 'Voir l\'image';
                        } else {
                            viewLink.textContent = 'Voir le fichier';
                        }
                        
                        // Remplacer les anciens liens s'il y en a
                        const parent = menuFileInput.parentNode;
                        const existingLinks = parent.querySelectorAll('a');
                        existingLinks.forEach(link => link.remove());
                        
                        parent.appendChild(viewLink);
                        
                        // Afficher un message de succès
                        const successMsg = document.createElement('p');
                        successMsg.className = 'upload-success';
                        successMsg.textContent = 'Fichier importé avec succès !';
                        
                        // Supprimer les messages précédents
                        const previousMsg = parent.querySelector('.upload-success');
                        if (previousMsg) {
                            previousMsg.remove();
                        }
                        
                        parent.appendChild(successMsg);
                    } else {
                        alert(response.data.message || 'Une erreur est survenue lors du téléversement.');
                    }
                } else {
                    alert('Une erreur est survenue lors du téléversement.');
                }
                
                // Masquer la barre de progression
                progressContainer.style.display = 'none';
            });
            
            xhr.addEventListener('error', function() {
                alert('Une erreur est survenue lors du téléversement.');
                progressContainer.style.display = 'none';
            });
            
            xhr.open('POST', ajaxurl, true);
            xhr.send(formData);
        }
    }
    
    // Les notes sont maintenant affichées directement, plus besoin de tooltips

    // Fonction pour améliorer l'UX tactile globale
    function initTouchEnhancements() {
        // Amélioration du feedback visuel pour les boutons d'action
        const actionButtons = document.querySelectorAll('.action-button');
        actionButtons.forEach(button => {
            // Feedback tactile amélioré avec vibration si disponible
            button.addEventListener('touchstart', function(e) {
                e.preventDefault();
                this.style.transform = 'scale(0.9)';
                this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
                
                // Vibration tactile légère si supportée
                if (navigator.vibrate) {
                    navigator.vibrate(10);
                }
            });
            
            button.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                }, 150);
            });
            
            // Meilleure gestion des événements tactiles
            button.addEventListener('touchmove', function(e) {
                // Annuler l'interaction si on sort du bouton
                const touch = e.touches[0];
                const rect = this.getBoundingClientRect();
                
                if (touch.clientX < rect.left || touch.clientX > rect.right ||
                    touch.clientY < rect.top || touch.clientY > rect.bottom) {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                }
            });
            
            // S'assurer que les boutons sont toujours cliquables
            button.style.pointerEvents = 'auto';
            button.style.touchAction = 'manipulation';
        });
        
        // Amélioration du scroll horizontal sur tablette
        const tableContainer = document.querySelector('.reservations-table-container');
        if (tableContainer) {
            let isScrolling = false;
            
            tableContainer.addEventListener('touchstart', () => {
                isScrolling = false;
            });
            
            tableContainer.addEventListener('touchmove', () => {
                isScrolling = true;
            });
            
            // Empêcher les clics accidentels pendant le scroll
            tableContainer.addEventListener('touchend', (e) => {
                if (isScrolling) {
                    e.preventDefault();
                }
            });
        }
        
        // Amélioration des liens email/téléphone
        const contactLinks = document.querySelectorAll('.customer-email, .customer-phone');
        contactLinks.forEach(link => {
            link.addEventListener('touchstart', function() {
                this.style.backgroundColor = 'rgba(181, 166, 146, 0.1)';
            });
            
            link.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 200);
            });
        });
    }

    // Fonction pour optimiser les filtres sur mobile/tablette
    function initResponsiveFilters() {
        const filterForm = document.querySelector('.filter-form');
        if (!filterForm) return;
        
        // Auto-focus sur le champ de recherche quand on ouvre les filtres sur tablette
        const searchInput = filterForm.querySelector('input[type="search"], input[name="s"]');
        if (searchInput && window.innerWidth >= 768 && window.innerWidth <= 1024) {
            // Délai pour éviter l'auto-zoom sur iOS
            setTimeout(() => {
                if (document.activeElement !== searchInput) {
                    searchInput.focus();
                }
            }, 500);
        }
        
        // Améliorer le comportement du formulaire sur tablette
        const submitButton = filterForm.querySelector('.filter-button');
        if (submitButton) {
            // Ajouter un indicateur de chargement sur tablette
            filterForm.addEventListener('submit', function() {
                submitButton.style.opacity = '0.7';
                submitButton.innerHTML = submitButton.innerHTML.replace(/Filtrer/, 'Chargement...');
            });
        }
    }

    // Initialiser les fonctionnalités au chargement du document
    $(document).ready(function() {
        initFileUpload();
        initTouchEnhancements();
        initResponsiveFilters();
    });

})(jQuery); 