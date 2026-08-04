<?php
/**
 * BAN Address Autocomplete
 * Aide de saisie d'adresse basée sur l'API Adresse (Base Adresse Nationale)
 * https://api-adresse.data.gouv.fr — API publique, gratuite, sans clé.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class BanAddressAutocomplete extends Module
{
    /** Pages front sur lesquelles on charge le script (formulaires d'adresse) */
    const TARGET_PAGES = ['address', 'order', 'order-opc', 'authentication', 'identity'];

    public function __construct()
    {
        $this->name = 'banaddressautocomplete';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'LittleSpyrit';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BAN Address Autocomplete');
        $this->description = $this->l("Aide de saisie d'adresse en France via l'API Adresse du gouvernement (data.gouv.fr) — gratuite, sans clé API, sans tracking tiers.");
        $this->confirmUninstall = $this->l('Êtes-vous sûr de vouloir désinstaller ce module ?');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && Configuration::updateValue('BANADDR_ENABLED', 1)
            && Configuration::updateValue('BANADDR_MIN_CHARS', 3)
            && Configuration::updateValue('BANADDR_LIMIT', 5)
            && Configuration::updateValue('BANADDR_COLOR_BG', '#ffffff')
            && Configuration::updateValue('BANADDR_COLOR_TEXT', '#333333')
            && Configuration::updateValue('BANADDR_COLOR_HOVER', '#f5f5f5')
            && Configuration::updateValue('BANADDR_COLOR_HOVER_TEXT', '#333333')
            && Configuration::updateValue('BANADDR_COLOR_BORDER', '#dddddd')
            && Configuration::updateValue('BANADDR_BORDER_RADIUS', 4)
            && Configuration::updateValue('BANADDR_FIELD_ADDRESS1', 'field-address1')
            && Configuration::updateValue('BANADDR_FIELD_POSTCODE', 'field-postcode')
            && Configuration::updateValue('BANADDR_FIELD_CITY', 'field-city')
            && Configuration::updateValue('BANADDR_FIELD_COUNTRY', 'field-id_country');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('BANADDR_ENABLED')
            && Configuration::deleteByName('BANADDR_MIN_CHARS')
            && Configuration::deleteByName('BANADDR_LIMIT')
            && Configuration::deleteByName('BANADDR_COLOR_BG')
            && Configuration::deleteByName('BANADDR_COLOR_TEXT')
            && Configuration::deleteByName('BANADDR_COLOR_HOVER')
            && Configuration::deleteByName('BANADDR_COLOR_HOVER_TEXT')
            && Configuration::deleteByName('BANADDR_COLOR_BORDER')
            && Configuration::deleteByName('BANADDR_BORDER_RADIUS')
            && Configuration::deleteByName('BANADDR_FIELD_ADDRESS1')
            && Configuration::deleteByName('BANADDR_FIELD_POSTCODE')
            && Configuration::deleteByName('BANADDR_FIELD_CITY')
            && Configuration::deleteByName('BANADDR_FIELD_COUNTRY');
    }

    /**
     * Page de configuration du module dans le BO
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit_banaddr')) {
            $enabled = (int) Tools::getValue('BANADDR_ENABLED');
            $minChars = max(1, (int) Tools::getValue('BANADDR_MIN_CHARS'));
            $limit = max(1, min(20, (int) Tools::getValue('BANADDR_LIMIT')));

            Configuration::updateValue('BANADDR_ENABLED', $enabled);
            Configuration::updateValue('BANADDR_MIN_CHARS', $minChars);
            Configuration::updateValue('BANADDR_LIMIT', $limit);

            foreach (['BANADDR_COLOR_BG', 'BANADDR_COLOR_TEXT', 'BANADDR_COLOR_HOVER', 'BANADDR_COLOR_HOVER_TEXT', 'BANADDR_COLOR_BORDER'] as $colorKey) {
                $value = trim((string) Tools::getValue($colorKey));

                if ($value === '') {
                    continue;
                }

                // Cas 1 : variable CSS, ex. --color-beige (on stocke tel quel)
                if (strpos($value, '--') === 0 && preg_match('/^--[a-zA-Z0-9_-]+$/', $value)) {
                    Configuration::updateValue($colorKey, $value);
                    continue;
                }

                // Cas 2 : code hexadécimal, avec ou sans # (on rajoute le # si absent)
                $hex = $value[0] === '#' ? $value : '#' . $value;
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
                    Configuration::updateValue($colorKey, $hex);
                }
            }

            $borderRadius = Tools::getValue('BANADDR_BORDER_RADIUS');
            if ($borderRadius !== false && $borderRadius !== '') {
                Configuration::updateValue('BANADDR_BORDER_RADIUS', max(0, min(50, (int) $borderRadius)));
            }

            foreach (['BANADDR_FIELD_ADDRESS1', 'BANADDR_FIELD_POSTCODE', 'BANADDR_FIELD_CITY', 'BANADDR_FIELD_COUNTRY'] as $fieldKey) {
                // On retire un éventuel "#" collé par erreur, on ne stocke que l'ID
                $value = ltrim((string) Tools::getValue($fieldKey), '# ');
                if ($value !== '') {
                    Configuration::updateValue($fieldKey, $value);
                }
            }

            $output .= $this->displayConfirmation($this->l('Paramètres enregistrés.'));
        }

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $fields_form_general = [
            'form' => [
                'legend' => [
                    'title' => $this->l('BAN Address Autocomplete'),
                    'icon' => 'icon-map-marker',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Activer l\'autocomplétion'),
                        'name' => 'BANADDR_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Nombre de caractères avant déclenchement'),
                        'name' => 'BANADDR_MIN_CHARS',
                        'class' => 'fixed-width-xs',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Nombre de suggestions affichées'),
                        'name' => 'BANADDR_LIMIT',
                        'class' => 'fixed-width-xs',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $fields_form_colors = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Apparence de la liste de suggestions'),
                    'icon' => 'icon-paint-brush',
                ],
                'description' => $this->l('Vous pouvez entrer un code couleur hexadécimal (ex : #f5f0e6) ou le nom d\'une variable CSS déjà définie sur votre thème (ex : --color-beige).'),
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Couleur de fond'),
                        'name' => 'BANADDR_COLOR_BG',
                        'hint' => $this->l('Fond de la liste déroulante de suggestions. Exemple : #ffffff ou --color-beige.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Couleur du texte'),
                        'name' => 'BANADDR_COLOR_TEXT',
                        'hint' => $this->l('Couleur du texte de chaque suggestion. Exemple : #333333 ou --color-text.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Couleur au survol'),
                        'name' => 'BANADDR_COLOR_HOVER',
                        'hint' => $this->l('Fond d\'une suggestion quand la souris passe dessus. Exemple : #f5f5f5 ou --color-hover.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Couleur du texte au survol'),
                        'name' => 'BANADDR_COLOR_HOVER_TEXT',
                        'hint' => $this->l('Couleur du texte quand la souris passe sur une suggestion. Exemple : #333333 ou --color-text-hover.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Couleur de bordure'),
                        'name' => 'BANADDR_COLOR_BORDER',
                        'hint' => $this->l('Bordure autour de la liste et entre les suggestions. Exemple : #dddddd ou --color-border.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Arrondi des bordures (px)'),
                        'name' => 'BANADDR_BORDER_RADIUS',
                        'class' => 'fixed-width-xs',
                        'hint' => $this->l('0 pour des angles droits, plus le chiffre est grand plus les coins sont arrondis. Valeur entre 0 et 50.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $fields_form_ids = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Champs du formulaire d\'adresse'),
                    'icon' => 'icon-code',
                ],
                'description' => $this->l('Indiquez l\'ID HTML de chaque champ, sans le caractère #.'),
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('ID du champ Adresse'),
                        'name' => 'BANADDR_FIELD_ADDRESS1',
                        'hint' => $this->l('Pour le trouver : clic droit sur le champ Adresse dans la page, puis Inspecter. Repérez l\'attribut id de la balise input dans le code affiché, et copiez sa valeur, sans le # et sans les guillemets. Par exemple, si vous voyez id égal field-alias, entrez : field-alias'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('ID du champ Code postal'),
                        'name' => 'BANADDR_FIELD_POSTCODE',
                        'hint' => $this->l('Même méthode : clic droit sur le champ Code postal, Inspecter, puis copiez la valeur de son attribut id, sans le # et sans les guillemets.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('ID du champ Ville'),
                        'name' => 'BANADDR_FIELD_CITY',
                        'hint' => $this->l('Même méthode : clic droit sur le champ Ville, Inspecter, puis copiez la valeur de son attribut id, sans le # et sans les guillemets.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('ID du champ Pays'),
                        'name' => 'BANADDR_FIELD_COUNTRY',
                        'hint' => $this->l('Optionnel, non utilisé pour le moment. Même méthode que les autres champs.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Enregistrer'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Context::getContext()->language->id;
        $helper->submit_action = 'submit_banaddr';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['BANADDR_ENABLED'] = Configuration::get('BANADDR_ENABLED');
        $helper->fields_value['BANADDR_MIN_CHARS'] = Configuration::get('BANADDR_MIN_CHARS');
        $helper->fields_value['BANADDR_LIMIT'] = Configuration::get('BANADDR_LIMIT');
        $helper->fields_value['BANADDR_COLOR_BG'] = Configuration::get('BANADDR_COLOR_BG');
        $helper->fields_value['BANADDR_COLOR_TEXT'] = Configuration::get('BANADDR_COLOR_TEXT');
        $helper->fields_value['BANADDR_COLOR_HOVER'] = Configuration::get('BANADDR_COLOR_HOVER');
        $helper->fields_value['BANADDR_COLOR_HOVER_TEXT'] = Configuration::get('BANADDR_COLOR_HOVER_TEXT');
        $helper->fields_value['BANADDR_COLOR_BORDER'] = Configuration::get('BANADDR_COLOR_BORDER');
        $helper->fields_value['BANADDR_BORDER_RADIUS'] = Configuration::get('BANADDR_BORDER_RADIUS');
        $helper->fields_value['BANADDR_FIELD_ADDRESS1'] = Configuration::get('BANADDR_FIELD_ADDRESS1');
        $helper->fields_value['BANADDR_FIELD_POSTCODE'] = Configuration::get('BANADDR_FIELD_POSTCODE');
        $helper->fields_value['BANADDR_FIELD_CITY'] = Configuration::get('BANADDR_FIELD_CITY');
        $helper->fields_value['BANADDR_FIELD_COUNTRY'] = Configuration::get('BANADDR_FIELD_COUNTRY');

        return $helper->generateForm([$fields_form_general, $fields_form_colors, $fields_form_ids]);
    }

    /**
     * Injecte le JS/CSS uniquement sur les pages contenant un formulaire d'adresse
     */
    public function hookDisplayHeader()
    {
        if (!Configuration::get('BANADDR_ENABLED')) {
            return;
        }

        if (!$this->isTargetPage()) {
            return;
        }

        Media::addJsDef([
            'banAddrConfig' => [
                'minChars' => (int) Configuration::get('BANADDR_MIN_CHARS'),
                'limit' => (int) Configuration::get('BANADDR_LIMIT'),
                'apiUrl' => 'https://api-adresse.data.gouv.fr/search/',
                'colors' => [
                    'bg' => Configuration::get('BANADDR_COLOR_BG'),
                    'text' => Configuration::get('BANADDR_COLOR_TEXT'),
                    'hover' => Configuration::get('BANADDR_COLOR_HOVER'),
                    'hoverText' => Configuration::get('BANADDR_COLOR_HOVER_TEXT'),
                    'border' => Configuration::get('BANADDR_COLOR_BORDER'),
                    'radius' => (int) Configuration::get('BANADDR_BORDER_RADIUS'),
                ],
                'fields' => [
                    'address1' => $this->getFieldConfig('BANADDR_FIELD_ADDRESS1', 'field-address1'),
                    'postcode' => $this->getFieldConfig('BANADDR_FIELD_POSTCODE', 'field-postcode'),
                    'city' => $this->getFieldConfig('BANADDR_FIELD_CITY', 'field-city'),
                    'country' => $this->getFieldConfig('BANADDR_FIELD_COUNTRY', 'field-id_country'),
                ],
            ],
        ]);

        $this->context->controller->registerStylesheet(
            'ban-address-autocomplete-css',
            'modules/' . $this->name . '/views/css/front.css'
        );

        $this->context->controller->registerJavascript(
            'ban-address-autocomplete-js',
            'modules/' . $this->name . '/views/js/front.js'
        );
    }

    /**
     * Récupère un ID de champ configuré, avec repli sur une valeur par défaut
     * si la config est vide (protège contre un champ laissé vide dans le BO).
     */
    protected function getFieldConfig($key, $default)
    {
        $value = trim((string) Configuration::get($key));

        return $value !== '' ? $value : $default;
    }

    /**
     * Détecte si la page courante est une page avec formulaire d'adresse.
     * page_name étant une propriété protégée sur FrontController, on passe
     * par la méthode publique getPageName(), avec un filet de sécurité sur
     * le nom de la classe du contrôleur au cas où.
     */
    protected function isTargetPage()
    {
        $controller = $this->context->controller;

        $page = '';
        if (method_exists($controller, 'getPageName')) {
            $page = (string) $controller->getPageName();
        }

        foreach (self::TARGET_PAGES as $target) {
            if ($page !== '' && strpos($page, $target) !== false) {
                return true;
            }
        }

        // Filet de sécurité : nom de la classe du contrôleur (toujours accessible)
        $className = get_class($controller);
        $targetClasses = ['AddressController', 'OrderController', 'OrderOpcController', 'AuthController', 'IdentityController'];
        foreach ($targetClasses as $targetClass) {
            if (stripos($className, $targetClass) !== false) {
                return true;
            }
        }

        return false;
    }
}
