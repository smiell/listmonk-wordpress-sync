<?php

/**
 * Class Listmonk_Synchronizer
 *
 * Configure the plugin synchronization features
 */
class Listmonk_Synchronizer {

	function __construct() {
		// Hook do przechwycenia zdarzenia złożenia zamówienia
		add_action('woocommerce_thankyou', [$this, 'send_to_listmonk']);
	}

    /**
     * Metoda do wysyłania danych do Listmonk po złożeniu zamówienia
     *
     * @param int $order_id ID zamówienia
     */
    public function send_to_listmonk($order_id) {
        // Pobierz dane zamówienia
        $order = wc_get_order($order_id);

        // Pobierz adres e-mail, imię i nazwisko klienta
        $email = wp_slash($order->get_billing_email());
        $first_name = ucfirst(strtolower(wp_slash($order->get_billing_first_name()))); // Zamiana pierwszej litery na dużą

        // Pobierz produkty z zamówienia
        $items = $order->get_items();
        $category_ids = array();
        $category_names = array(); // Dodaj nową tablicę na nazwy kategorii

        // Pobierz kategorie produktów
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $categories = get_the_terms($product_id, 'product_cat');

            if ($categories) {
                foreach ($categories as $category) {
                    $category_ids[] = $category->term_id;
                    $category_names[] = $category->name; // Dodaj nazwę kategorii do tablicy
                }
            }
        }

        // Usuń ewentualne duplikaty z tablicy
        $category_ids = array_unique($category_ids);
        $category_names = array_unique($category_names); // Usuń duplikaty z nazw kategorii

        // Pobierz ustawienia zapisane w opcjach
        $options = get_option('zhngrupa-listmonk-synchronizer');
        $listmonk_active = isset($options['listmonk-active']) ? $options['listmonk-active'] : false;

        if ($listmonk_active) {
            // Twój endpoint API Listmonk
            $listmonk_api_endpoint = isset($options['listmonk-public-url']) ? trailingslashit($options['listmonk-public-url']) . 'api/subscribers' : '';

            $preconfirm_subscription = isset($options['listmonk-preconfirm_subscription']) && $options['listmonk-preconfirm_subscription'] ? true : false;

            // Dane do wysłania
            $data = array(
                'email' => $email,
                'name' => $first_name,
                'status' => 'enabled',
                'lists' => [intval($options['listmonk-list-id'])], // Identyfikator listy
                'preconfirm_subscriptions' => $preconfirm_subscription,
                'attribs' => array(
                    'category_ids' => $category_ids,
                    'category_names' => $category_names // Dodaj nazwy kategorii do atrybutów
                ),
            );

            // Dodajemy logowanie
            error_log('Dane wysyłane do Listmonk: ' . json_encode($data));

            // Wysłanie danych do API Listmonk
            $response = wp_remote_post($listmonk_api_endpoint, array(
                'body' => json_encode($data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($options['listmonk-user'] . ':' . $options['listmonk-user-password'])
                ),
            ));

            // Sprawdź odpowiedź API
            if (is_wp_error($response)) {
                // Błąd - dodaj notatkę o błędzie do zamówienia
                $order->add_order_note('Błąd Listmonk Synchronizer: ' . $response->get_error_message());

            } else {
                // Odpowiedź API
                $body = wp_remote_retrieve_body($response);
                $status_code = wp_remote_retrieve_response_code($response);

                // Jeśli api zwróciło 200 http to subskrybent został dodany
                if ($status_code == 200) {
                    // Kod 200 - dodaj notatkę o dodaniu subskrybenta do zamówienia
                    $order->add_order_note('Listmonk Synchronizer: Nowy subskrybent został dodany do Listmonk. Dane: ' . json_encode($data));
                }

                // Jeśli api zwróciło 409 http to subskrybent już istniał
                if ($status_code == 409) {
                    // Kod 409 - dodaj notatkę, że użytkownik już istnieje
                    $order->add_order_note('Listmonk Synchronizer: Użytkownik już istnieje w liście.');
                }

                // Jeśli api zwróciło kod http inny niż 200 lub 409 http pokaż błąd i logi
                if ($status_code != 200 && $status_code != 409) {
                    // Dodaj info o tym, że wystąpił jakiś inny błąd, dodaj logi do notatki w zamówieniu
                    $order->add_order_note('Błąd Listmonk Synchronizer: Kod HTTP: ' . $status_code . ', Odpowiedź API: ' . $body . ' Dane: ' . json_encode($data));
                }
            }

            // Zapisz zmiany w zamówieniu
            $order->save();
        }
    }

}