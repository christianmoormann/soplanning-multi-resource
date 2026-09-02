<?php

// Definition des tools exposes a l'IA (format Ollama / OpenAI function calling).
// Descriptions volontairement courtes pour minimiser les tokens de contexte (performance CPU).

class SoplanningTools {

    public static function get() {
        return array(
            self::rechercherUtilisateur(),
            self::rechercherProjet(),
            self::creerTache(),
            self::modifierTache(),
            self::supprimerTache(),
            self::listerTaches(),
            self::creerProjet(),
            self::demanderClarification(),
        );
    }

    private static function rechercherUtilisateur() {
        return array('type' => 'function', 'function' => array(
            'name' => 'rechercher_utilisateur',
            'description' => 'Trouve l ID d un utilisateur a partir de son nom.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'nom' => array('type' => 'string'),
                ),
                'required' => array('nom'),
            ),
        ));
    }

    private static function rechercherProjet() {
        return array('type' => 'function', 'function' => array(
            'name' => 'rechercher_projet',
            'description' => 'Trouve l ID d un projet a partir de son nom.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'nom' => array('type' => 'string'),
                ),
                'required' => array('nom'),
            ),
        ));
    }

    private static function creerTache() {
        return array('type' => 'function', 'function' => array(
            'name' => 'creer_tache',
            'description' => 'Cree une tache dans le planning pour un utilisateur sur un projet.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'user_id'    => array('type' => 'string', 'description' => 'ID utilisateur'),
                    'project_id' => array('type' => 'string', 'description' => 'ID projet'),
                    'start_date' => array('type' => 'string', 'description' => 'YYYY-MM-DD'),
                    'end_date'   => array('type' => 'string', 'description' => 'YYYY-MM-DD, defaut = start_date'),
                    'start_time' => array('type' => 'string', 'description' => 'HH:MM'),
                    'end_time'   => array('type' => 'string', 'description' => 'HH:MM'),
                    'title'      => array('type' => 'string'),
                    'comment'    => array('type' => 'string'),
                    'place_id'   => array('type' => 'string'),
                    'resource_id'=> array('type' => 'string'),
                ),
                'required' => array('user_id', 'project_id', 'start_date'),
            ),
        ));
    }

    private static function modifierTache() {
        return array('type' => 'function', 'function' => array(
            'name' => 'modifier_tache',
            'description' => 'Modifie une tache existante. Utilise lister_taches si tu ne connais pas l ID.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'task_id'    => array('type' => 'integer'),
                    'user_id'    => array('type' => 'string'),
                    'project_id' => array('type' => 'string'),
                    'start_date' => array('type' => 'string', 'description' => 'YYYY-MM-DD'),
                    'end_date'   => array('type' => 'string', 'description' => 'YYYY-MM-DD'),
                    'start_time' => array('type' => 'string', 'description' => 'HH:MM'),
                    'end_time'   => array('type' => 'string', 'description' => 'HH:MM'),
                    'title'      => array('type' => 'string'),
                    'comment'    => array('type' => 'string'),
                ),
                'required' => array('task_id'),
            ),
        ));
    }

    private static function supprimerTache() {
        return array('type' => 'function', 'function' => array(
            'name' => 'supprimer_tache',
            'description' => 'Supprime une tache. Utilise lister_taches si tu ne connais pas l ID.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'task_id' => array('type' => 'integer'),
                ),
                'required' => array('task_id'),
            ),
        ));
    }

    private static function listerTaches() {
        return array('type' => 'function', 'function' => array(
            'name' => 'lister_taches',
            'description' => 'Liste les taches avec filtres. Au moins un filtre requis.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'user_id'    => array('type' => 'string'),
                    'project_id' => array('type' => 'string'),
                    'start_date' => array('type' => 'string', 'description' => 'YYYY-MM-DD'),
                    'end_date'   => array('type' => 'string', 'description' => 'YYYY-MM-DD'),
                ),
                'required' => array(),
            ),
        ));
    }

    private static function creerProjet() {
        return array('type' => 'function', 'function' => array(
            'name' => 'creer_projet',
            'description' => 'Cree un nouveau projet.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'project_id' => array('type' => 'string', 'description' => 'ID unique sans espaces'),
                    'name'       => array('type' => 'string'),
                    'owner_id'   => array('type' => 'string', 'description' => 'ID utilisateur proprietaire'),
                    'delivery'   => array('type' => 'string', 'description' => 'YYYY-MM-DD'),
                    'comment'    => array('type' => 'string'),
                ),
                'required' => array('project_id', 'name', 'owner_id'),
            ),
        ));
    }

    private static function demanderClarification() {
        return array('type' => 'function', 'function' => array(
            'name' => 'demander_clarification',
            'description' => 'Pose une question a l utilisateur pour lever une ambiguite.',
            'parameters' => array('type' => 'object',
                'properties' => array(
                    'question' => array('type' => 'string'),
                    'options'  => array('type' => 'array', 'items' => array('type' => 'string')),
                ),
                'required' => array('question'),
            ),
        ));
    }
}
