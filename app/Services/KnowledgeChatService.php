<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KnowledgeChatService
{
    public function __construct(private AiAnswerService $ai)
    {
    }

    private function detectAnswerLanguage(string $question): string
    {
        $normalized = ' ' . Str::lower(preg_replace('/\s+/', ' ', $question)) . ' ';
        $ascii = ' ' . Str::lower(Str::ascii(preg_replace('/\s+/', ' ', $question))) . ' ';

        $scores = [
            'Arabic' => 0,
            'French' => 0,
            'Spanish' => 0,
            'Portuguese' => 0,
            'Dutch' => 0,
        ];

        if (preg_match('/\p{Arabic}/u', $question)) {
            $scores['Arabic'] += 5;
        }

        if (str_contains($ascii, ' contribui') && (str_contains($ascii, ' o ') || str_contains($ascii, ' as ') || str_contains($ascii, ' sistema '))) {
            $scores['Portuguese'] += 4;
        }

        if (str_contains($question, '¿') || str_contains($question, '¡')) {
            $scores['Spanish'] += 4;
        }

        if (preg_match('/[ãõçáéíóúâêôà]/iu', $question)) {
            $scores['Portuguese'] += 2;
        }

        foreach ([
            ' quel ', ' quelle ', ' quels ', ' quelles ', ' pourquoi ', ' comment ', ' peut ', ' peuvent ',
            ' dans ', ' avec ', ' pour ', ' est-ce ', ' fonction ', ' utilise ', ' utiliser ',
        ] as $signal) {
            if (str_contains($normalized, $signal)) {
                $scores['French']++;
            }
        }

        foreach ([
            ' que es ', ' cual ', ' cuales ', ' por que ', ' para que ', ' cuando ', ' puede ', ' pueden ',
            ' como ', ' con ', ' cuando se ', ' se usa ', ' usar ', ' proposito ', ' funcion ', ' el ', ' la ', ' las ', ' los ',
        ] as $signal) {
            if (str_contains($ascii, $signal)) {
                $scores['Spanish']++;
            }
        }

        foreach ([
            ' o que ', ' qual ', ' quais ', ' por que ', ' para que ', ' quando ', ' pode ', ' podem ',
            ' com ', ' usado ', ' usada ', ' usar ', ' finalidade ', ' funcao ', ' contribuicao ', ' contribuicoes ',
        ] as $signal) {
            if (str_contains($ascii, $signal)) {
                $scores['Portuguese']++;
            }
        }

        foreach ([
            ' hoe ', ' wat ', ' welke ', ' waarom ', ' wanneer ', ' waar ', ' kan ', ' kunnen ',
            ' wordt ', ' worden ', ' gebruikt ',
            ' berekent ', ' berekenen ', ' aanvraag ', ' aanvragen ', ' uitkering ', ' uitkeringen ',
            ' bijdragen ', ' premie ', ' premies ', ' ingesteld ',
        ] as $signal) {
            if (str_contains($ascii, $signal)) {
                $scores['Dutch']++;
            }
        }

        $bestLanguage = collect($scores)->sortDesc()->keys()->first();
        $bestScore = $bestLanguage ? $scores[$bestLanguage] : 0;

        return $bestScore > 0 ? $bestLanguage : 'English';
    }

    /**
     * @param array<string, string> $replace
     */
    private function localizedMessage(string $key, string $language, array $replace = []): string
    {
        $messages = [
            'English' => [
                'specific_question' => 'Ask a more specific product question so I can search the approved knowledge base.',
                'no_match' => 'I could not find approved product knowledge that matches that question yet. Upload and approve a source that covers this topic, then ask again.',
                'module_intro' => ':product includes the following modules listed in the approved fact sheet inventory:',
                'source' => 'Source',
                'grounded_only' => 'This answer is grounded only in approved uploaded product knowledge.',
                'yes_supports' => 'Yes. The approved knowledge base indicates that :product supports :topic.',
                'supports_intro' => ':product supports :topic through the following capabilities:',
                'sources_used' => 'Sources used',
                'grounded_relevant' => 'This answer is grounded in all approved source chunks that met the relevance threshold for the question.',
            ],
            'Arabic' => [
                'specific_question' => 'يرجى طرح سؤال أكثر تحديداً عن المنتج حتى أتمكن من البحث في قاعدة المعرفة المعتمدة.',
                'no_match' => 'لم أجد حتى الآن معرفة منتج معتمدة تطابق هذا السؤال. يرجى تحميل مصدر يغطي هذا الموضوع واعتماده، ثم طرح السؤال مرة أخرى.',
                'module_intro' => 'يتضمن :product الوحدات التالية المدرجة في نشرة المنتج المعتمدة:',
                'source' => 'المصدر',
                'grounded_only' => 'تستند هذه الإجابة فقط إلى معرفة المنتج التي تم تحميلها واعتمادها.',
                'yes_supports' => 'نعم. تشير قاعدة المعرفة المعتمدة إلى أن :product يدعم :topic.',
                'supports_intro' => 'يدعم :product موضوع :topic من خلال الإمكانات التالية:',
                'sources_used' => 'المصادر المستخدمة',
                'grounded_relevant' => 'تستند هذه الإجابة إلى جميع المقاطع المعتمدة التي بلغت حد الصلة المطلوب لهذا السؤال.',
            ],
            'French' => [
                'specific_question' => 'Posez une question produit plus precise afin que je puisse rechercher dans la base de connaissances approuvee.',
                'no_match' => 'Je n ai pas encore trouve de connaissance produit approuvee correspondant a cette question. Ajoutez et approuvez une source couvrant ce sujet, puis reposez la question.',
                'module_intro' => ':product comprend les modules suivants listes dans la fiche produit approuvee:',
                'source' => 'Source',
                'grounded_only' => 'Cette reponse est fondee uniquement sur les connaissances produit televersees et approuvees.',
                'yes_supports' => 'Oui. La base de connaissances approuvee indique que :product prend en charge :topic.',
                'supports_intro' => ':product prend en charge :topic au moyen des capacites suivantes:',
                'sources_used' => 'Sources utilisees',
                'grounded_relevant' => 'Cette reponse est fondee sur tous les extraits approuves qui ont atteint le seuil de pertinence pour la question.',
            ],
            'Spanish' => [
                'specific_question' => 'Haga una pregunta de producto mas especifica para que pueda buscar en la base de conocimiento aprobada.',
                'no_match' => 'Aun no encontre conocimiento de producto aprobado que coincida con esa pregunta. Cargue y apruebe una fuente que cubra este tema y vuelva a preguntar.',
                'module_intro' => ':product incluye los siguientes modulos listados en la ficha de producto aprobada:',
                'source' => 'Fuente',
                'grounded_only' => 'Esta respuesta se basa solo en conocimiento de producto cargado y aprobado.',
                'yes_supports' => 'Si. La base de conocimiento aprobada indica que :product admite :topic.',
                'supports_intro' => ':product admite :topic mediante las siguientes capacidades:',
                'sources_used' => 'Fuentes utilizadas',
                'grounded_relevant' => 'Esta respuesta se basa en todos los fragmentos aprobados que alcanzaron el umbral de relevancia para la pregunta.',
            ],
            'Portuguese' => [
                'specific_question' => 'Faca uma pergunta de produto mais especifica para que eu possa pesquisar na base de conhecimento aprovada.',
                'no_match' => 'Ainda nao encontrei conhecimento de produto aprovado que corresponda a essa pergunta. Carregue e aprove uma fonte que cubra esse tema e pergunte novamente.',
                'module_intro' => ':product inclui os seguintes modulos listados na ficha de produto aprovada:',
                'source' => 'Fonte',
                'grounded_only' => 'Esta resposta se baseia apenas em conhecimento de produto carregado e aprovado.',
                'yes_supports' => 'Sim. A base de conhecimento aprovada indica que :product oferece suporte a :topic.',
                'supports_intro' => ':product oferece suporte a :topic por meio das seguintes capacidades:',
                'sources_used' => 'Fontes utilizadas',
                'grounded_relevant' => 'Esta resposta se baseia em todos os trechos aprovados que atingiram o limite de relevancia para a pergunta.',
            ],
            'Dutch' => [
                'specific_question' => 'Stel een specifiekere productvraag zodat ik in de goedgekeurde kennisbank kan zoeken.',
                'no_match' => 'Ik heb nog geen goedgekeurde productkennis gevonden die bij deze vraag past. Upload en keur een bron goed die dit onderwerp behandelt en stel de vraag opnieuw.',
                'module_intro' => ':product bevat de volgende modules die in de goedgekeurde productfiche staan:',
                'source' => 'Bron',
                'grounded_only' => 'Dit antwoord is alleen gebaseerd op goedgekeurde geuploade productkennis.',
                'yes_supports' => 'Ja. De goedgekeurde kennisbank geeft aan dat :product :topic ondersteunt.',
                'supports_intro' => ':product ondersteunt :topic via de volgende mogelijkheden:',
                'sources_used' => 'Gebruikte bronnen',
                'grounded_relevant' => 'Dit antwoord is gebaseerd op alle goedgekeurde fragmenten die de relevantiedrempel voor de vraag haalden.',
            ],
        ];

        $message = $messages[$language][$key] ?? $messages['English'][$key] ?? '';

        foreach ($replace as $search => $value) {
            $message = str_replace(':' . $search, $value, $message);
        }

        return $message;
    }

    /**
     * @return array{answer: string, matches: \Illuminate\Support\Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}>}
     */
    public function answer(string $question, ?int $productId = null): array
    {
        $answerLanguage = $this->detectAnswerLanguage($question);

        if ($this->isModuleListQuestion($question)) {
            $moduleList = $this->moduleListAnswer($productId, $answerLanguage);

            if ($moduleList !== null) {
                return $moduleList;
            }
        }

        $terms = $this->questionTerms($question);
        $phrases = $this->questionPhrases($question);
        $profile = $this->questionConceptProfile($question, $terms, $phrases);

        if ($terms->isEmpty()) {
            return [
                'answer' => $this->localizedMessage('specific_question', $answerLanguage),
                'matches' => collect(),
                'answer_language' => $answerLanguage,
                'retrieval_terms' => [],
            ];
        }

        $chunks = KnowledgeChunk::query()
            ->with('sourceDocument', 'product')
            ->where('approval_status', 'approved')
            ->when($productId, fn ($query) => $query->where('product_id', $productId))
            ->where(function ($query) use ($terms, $phrases) {
                foreach ($terms->merge($phrases)->unique() as $term) {
                    $query->orWhere('chunk_title', 'like', '%' . $term . '%')
                        ->orWhere('chunk_text', 'like', '%' . $term . '%')
                        ->orWhere('citation_label', 'like', '%' . $term . '%');
                }
            })
            ->limit(1200)
            ->get();

        $ranked = $chunks
            ->map(fn (KnowledgeChunk $chunk) => $this->rankChunk($chunk, $terms, $phrases, $profile))
            ->filter(fn (array $match) => $match['score'] > 0)
            ->sortByDesc('score')
            ->values();

        if ($ranked->isEmpty()) {
            return [
                'answer' => $this->localizedMessage('no_match', $answerLanguage),
                'matches' => collect(),
                'answer_language' => $answerLanguage,
                'retrieval_terms' => $terms->merge($phrases)->unique()->values()->all(),
            ];
        }

        $strongMatches = $this->strongMatches($ranked, $question);

        $fallbackAnswer = $this->composeAnswer($question, $strongMatches, $answerLanguage);
        $finalAnswer = $this->shouldUseControlledAnswer($terms, $phrases)
            ? $fallbackAnswer
            : $this->ai->draft($question, $strongMatches, $fallbackAnswer, $answerLanguage);

        return [
            'answer' => $finalAnswer,
            'matches' => $strongMatches,
            'answer_language' => $answerLanguage,
            'retrieval_terms' => $terms->merge($phrases)->unique()->values()->all(),
            'ai_provider' => $this->shouldUseControlledAnswer($terms, $phrases) ? 'controlled' : null,
        ];
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $ranked
     * @return Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}>
     */
    private function strongMatches(Collection $ranked, string $question): Collection
    {
        if ($ranked->isEmpty()) {
            return $ranked;
        }

        $topScore = max(1, (int) $ranked->first()['score']);
        $floor = $this->isBroadQuestion($question) ? 10 : max(20, (int) floor($topScore * 0.25));
        $normalizedQuestion = Str::lower(Str::ascii($question));

        if (str_contains(Str::lower($question), 'position budget')) {
            $floor = 100;
        }

        if (
            (str_contains($normalizedQuestion, 'cotisation') || str_contains($normalizedQuestion, 'contribution') || str_contains($normalizedQuestion, 'contribui'))
            && str_contains($normalizedQuestion, 'calcul')
        ) {
            $floor = max(500, (int) floor($topScore * 0.45));
            $limit = 12;
        } elseif (
            (str_contains($normalizedQuestion, 'benefit') || str_contains($normalizedQuestion, 'prestacion') || str_contains($normalizedQuestion, 'prestation'))
            && (str_contains($normalizedQuestion, 'calcul') || str_contains($normalizedQuestion, 'configur'))
        ) {
            $floor = max(450, (int) floor($topScore * 0.45));
            $limit = 12;
        } else {
            $limit = 30;
        }

        $matches = $ranked;

        if ($this->isComplianceManagementQuestion($question)) {
            $floor = max($floor, (int) floor($topScore * 0.45), 180);
            $limit = 10;
            $matches = $matches->filter(function (array $match) {
                return $this->chunkIsComplianceRelevant($match['chunk']);
            });
        }

        return $matches
            ->filter(fn (array $match) => $match['score'] >= $floor)
            ->take($limit)
            ->values();
    }

    private function isBroadQuestion(string $question): bool
    {
        $question = Str::lower($question);

        return str_contains($question, 'all')
            || str_contains($question, 'complete')
            || str_contains($question, 'tell me about')
            || str_contains($question, 'explain')
            || str_contains($question, 'overview')
            || str_contains($question, 'describe');
    }

    private function isComplianceManagementQuestion(string $question): bool
    {
        $normalized = Str::lower(Str::ascii($question));

        return str_contains($normalized, 'compliance')
            || str_contains($normalized, 'delinquency')
            || str_contains($normalized, 'arrears')
            || str_contains($normalized, 'penalty')
            || str_contains($normalized, 'audit');
    }

    private function chunkIsComplianceRelevant(KnowledgeChunk $chunk): bool
    {
        $haystack = Str::lower(Str::ascii(implode(' ', [
            $chunk->chunk_title,
            $chunk->chunk_text,
            $chunk->business_area,
            $chunk->sourceDocument?->title,
        ])));

        return ($chunk->business_area ?? '') === 'compliance'
            || Str::contains($haystack, ['compliance', 'delinquency', 'arrears', 'penalty', 'penalties', 'liabilities', 'audit', 'inspection', 'lawsuit']);
    }

    private function isModuleListQuestion(string $question): bool
    {
        $question = Str::lower($question);

        return str_contains($question, 'module')
            && (str_contains($question, 'complete') || str_contains($question, 'all ') || str_contains($question, 'list'));
    }

    /**
     * @return null|array{answer: string, matches: \Illuminate\Support\Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}>}
     */
    private function moduleListAnswer(?int $productId, string $answerLanguage): ?array
    {
        $inventoryChunk = KnowledgeChunk::query()
            ->with('sourceDocument', 'product')
            ->where('approval_status', 'approved')
            ->when($productId, fn ($query) => $query->where('product_id', $productId))
            ->where('chunk_text', 'like', '%Position%Budgeting%Control%')
            ->where('chunk_text', 'like', '%Employee%Self-Service%')
            ->orderBy('id')
            ->first();

        if (! $inventoryChunk) {
            return null;
        }

        $modules = $this->hrmsModuleInventory();
        $productName = $inventoryChunk->product?->name ?? 'the selected product';
        $answer = $this->localizedMessage('module_intro', $answerLanguage, ['product' => $productName]) . "\n\n";

        foreach ($modules as $index => $module) {
            $answer .= ($index + 1) . '. ' . $module . "\n";
        }

        $answer .= "\n" . $this->localizedMessage('source', $answerLanguage) . ': ' . ($inventoryChunk->citation_label ?: $inventoryChunk->chunk_title) . '.';
        $answer .= "\n\n" . $this->localizedMessage('grounded_only', $answerLanguage);

        return [
            'answer' => $answer,
            'matches' => collect([[
                'chunk' => $inventoryChunk,
                'excerpt' => 'Module inventory from the approved HRMS fact sheet.',
                'score' => 1000,
            ]]),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function questionTerms(string $question): Collection
    {
        $stopWords = collect([
            'about', 'after', 'also', 'does', 'from', 'have', 'into', 'that', 'their', 'there',
            'these', 'this', 'what', 'when', 'where', 'which', 'with', 'would', 'your', 'interact',
            'purpose', 'used', 'uses', 'using', 'use', 'can', 'explain', 'through', 'key',
            'comment', 'les', 'des', 'une', 'sont', 'elle', 'elles', 'ils', 'est', 'dans', 'pour',
            'como', 'con', 'las', 'los', 'una', 'son', 'esta', 'este', 'para',
            'qual', 'quais', 'como', 'com', 'uma', 'sao', 'esta', 'este', 'para', 'sistema',
            'hoe', 'wat', 'welke', 'waarom', 'wanneer', 'waar', 'het', 'een', 'voor', 'met', 'wordt', 'worden',
            'ssas', 'hrms', 'ebpc', 'erms', 'product', 'system', 'module',
            'social', 'security', 'administration', 'administrations', 'support', 'supports',
            'management',
        ]);

        $normalizedQuestion = Str::lower(Str::ascii($question));
        $seedTerms = $this->translatedConceptTerms($question);
        $benefitMeansAdvantage = $this->benefitMeansAdvantage($question);

        return collect(preg_split('/[^a-z0-9]+/i', $normalizedQuestion))
            ->filter(fn (?string $term) => is_string($term) && (strlen($term) >= 3 || in_array($term, ['id'], true)))
            ->map(fn (string $term) => strlen($term) > 4 ? Str::singular($term) : $term)
            ->reject(fn (string $term) => $stopWords->contains($term))
            ->merge($seedTerms)
            ->flatMap(fn (string $term) => $this->expandedSearchTerms($term))
            ->reject(fn (string $term) => $benefitMeansAdvantage && in_array($term, ['benefit', 'benefits'], true))
            ->unique()
            ->take(28)
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function translatedConceptTerms(string $question): Collection
    {
        $benefitMeansAdvantage = $this->benefitMeansAdvantage($question);
        $normalized = ' ' . Str::lower(Str::ascii(preg_replace('/\s+/', ' ', $question))) . ' ';
        $unicode = ' ' . mb_strtolower(preg_replace('/\s+/', ' ', $question), 'UTF-8') . ' ';

        $concepts = [
            'benefit' => [
                'prestation', 'prestations', 'prestacion', 'prestaciones', 'beneficio', 'beneficios',
                'beneficio', 'beneficios', 'pension', 'pensions', 'allocation', 'claimant', 'claim',
                'uitkering', 'uitkeringen', 'pensioen', 'pensioenen', 'gerechtigde',
                'prestação', 'prestações', 'benefício', 'benefícios', 'pensão',
                'استحقاق', 'استحقاقات', 'مزايا', 'معاش', 'معاشات',
            ],
            'claim' => [
                'solicitud', 'solicitudes', 'demande', 'demandes', 'reclamation', 'reclamacion',
                'solicitacao', 'solicitacoes', 'pedido', 'pedidos',
                'aanvraag', 'aanvragen',
                'طلب', 'طلبات', 'مطالبة', 'مطالبات',
            ],
            'contribution' => [
                'cotisation', 'cotisations', 'contribucion', 'contribuciones', 'contribuicao', 'contribuicoes',
                'bijdrage', 'bijdragen', 'premie', 'premies',
                'contribuição', 'contribuições', 'aporte', 'aportes',
                'اشتراك', 'اشتراكات', 'مساهمة', 'مساهمات',
            ],
            'registration' => [
                'registration', 'adhesion', 'affiliation', 'inscription', 'enregistrement', 'registro',
                'registratie', 'inschrijving', 'aanmelding',
                'registracion', 'cadastro', 'inscricao', 'inscrição',
                'تسجيل',
            ],
            'compliance' => [
                'compliance', 'conformite', 'conformidad', 'cumplimiento', 'conformidade', 'delinquency',
                'arrears', 'penalty', 'penalties', 'audit', 'inspection', 'lawsuit',
                'naleving', 'achterstand', 'achterstanden', 'boete', 'boetes', 'controle', 'inspectie', 'rechtszaak',
                'امتثال', 'غرامة', 'غرامات', 'تدقيق', 'تفتيش',
            ],
            'payment' => [
                'payment', 'payments', 'paiement', 'paiements', 'pago', 'pagos', 'pagamento', 'pagamentos',
                'cheque', 'bank file', 'receipt', 'recibo',
                'betaling', 'betalingen', 'kwitantie', 'bankbestand',
                'دفع', 'دفعات', 'سداد',
            ],
            'configuration' => [
                'configuration', 'configure', 'configured', 'configurer', 'parametre', 'parametres',
                'configurar', 'configurado', 'parametro', 'parametros', 'configurar', 'parametro', 'parametros',
                'configuratie', 'instellen', 'ingesteld', 'parameter', 'parameters', 'regel', 'regels',
                'configuração', 'configurado', 'parâmetro', 'parâmetros',
                'إعداد', 'إعدادات', 'تهيئة', 'معاملات',
            ],
            'calculation' => [
                'calculation', 'calculate', 'calcul', 'calcule', 'calculer', 'calcula', 'calcular', 'calculo',
                'berekening', 'berekeningen', 'berekenen', 'berekent', 'berekend',
                'cálculo', 'calcula', 'calcular',
                'حساب', 'يحسب', 'تحسب',
            ],
            'eligibility' => [
                'eligibility', 'eligible', 'admissibilite', 'eligible', 'elegibilidad', 'elegible',
                'elegibilidade', 'elegivel', 'qualifying', 'entitlement',
                'geschiktheid', 'in aanmerking', 'recht', 'aanspraak',
                'أهلية', 'مؤهل', 'استحقاق',
            ],
            'document' => [
                'letter', 'letters', 'document', 'documents', 'template', 'certificate', 'notice',
                'lettre', 'carta', 'cartas', 'plantilla', 'modelo', 'documento',
                'brief', 'brieven', 'sjabloon', 'document', 'documenten', 'certificaat', 'kennisgeving',
                'خطاب', 'رسالة', 'قالب', 'وثيقة',
            ],
            'procedure' => [
                'how to', 'comment faire', 'como hacer', 'como se hace', 'como criar', 'pasos', 'etapes',
                'steps', 'click', 'select', 'approve', 'submit',
                'hoe doe ik', 'hoe maak ik', 'stappen', 'klik', 'selecteer', 'goedkeuren', 'indienen',
                'خطوات', 'كيفية',
            ],
        ];

        return collect($concepts)
            ->reject(fn (array $signals, string $concept) => $benefitMeansAdvantage && $concept === 'benefit')
            ->flatMap(function (array $signals, string $concept) use ($normalized, $unicode) {
                return collect($signals)
                    ->contains(fn (string $signal) => str_contains($normalized, Str::lower(Str::ascii($signal))) || str_contains($unicode, mb_strtolower($signal, 'UTF-8')))
                    ? [$concept]
                    : [];
            })
            ->values();
    }

    private function benefitMeansAdvantage(string $question): bool
    {
        $normalized = Str::lower(Str::ascii(preg_replace('/\s+/', ' ', $question)));

        return (bool) preg_match('/\b(key\s+)?benefits?\s+(of|from|for)\b/', $normalized)
            || (str_contains($normalized, 'advantages of') || str_contains($normalized, 'value of'));
    }

    /**
     * @return array<int, string>
     */
    private function expandedSearchTerms(string $term): array
    {
        $dictionary = [
            'adhesion' => ['registration', 'enrollment'],
            'affiliation' => ['registration', 'enrollment'],
            'allocation' => ['benefit', 'benefits'],
            'assure' => ['insured', 'person'],
            'assures' => ['insured', 'person'],
            'beneficiaire' => ['beneficiary'],
            'beneficiaires' => ['beneficiary'],
            'calcul' => ['calculation'],
            'calcula' => ['calculation'],
            'calcular' => ['calculation'],
            'calculee' => ['calculation'],
            'calculees' => ['calculation'],
            'calcule' => ['calculation'],
            'calcules' => ['calculation'],
            'calculated' => ['calculation'],
            'calculo' => ['calculation'],
            'calculado' => ['calculation'],
            'calculada' => ['calculation'],
            'calculados' => ['calculation'],
            'calculadas' => ['calculation'],
            'berekent' => ['calculation'],
            'berekenen' => ['calculation'],
            'berekening' => ['calculation'],
            'berekeningen' => ['calculation'],
            'berekend' => ['calculation'],
            'configurar' => ['configuration', 'configured'],
            'configura' => ['configuration', 'configured'],
            'configurado' => ['configuration', 'configured'],
            'instellen' => ['configuration', 'configured'],
            'ingesteld' => ['configuration', 'configured'],
            'configuratie' => ['configuration'],
            'parametro' => ['parameter', 'configuration'],
            'parametros' => ['parameter', 'parameters', 'configuration'],
            'parameters' => ['parameter', 'parameters', 'configuration'],
            'cotisation' => ['contribution'],
            'cotisations' => ['contribution', 'contributions'],
            'contribucion' => ['contribution'],
            'contribuciones' => ['contribution', 'contributions'],
            'contribuicao' => ['contribution'],
            'contribuica' => ['contribution'],
            'contribuico' => ['contribution'],
            'contribuicoes' => ['contribution', 'contributions'],
            'bijdrage' => ['contribution'],
            'bijdragen' => ['contribution', 'contributions'],
            'premie' => ['contribution'],
            'premies' => ['contribution', 'contributions'],
            'declaration' => ['filing', 'declaration'],
            'declaracion' => ['filing', 'declaration'],
            'emploi' => ['employment'],
            'employeur' => ['employer'],
            'employe' => ['employee'],
            'employes' => ['employee', 'employees'],
            'invalidite' => ['disability', 'invalidity'],
            'invalidez' => ['disability', 'invalidity'],
            'maladie' => ['medical', 'sickness'],
            'medecin' => ['doctor', 'medical'],
            'medicale' => ['medical'],
            'paiement' => ['payment'],
            'paiements' => ['payment', 'payments'],
            'pago' => ['payment'],
            'pagos' => ['payment', 'payments'],
            'pagamento' => ['payment'],
            'pagamentos' => ['payment', 'payments'],
            'pension' => ['pension', 'benefit'],
            'pensions' => ['pension', 'benefits'],
            'pensioen' => ['pension', 'benefit'],
            'pensioenen' => ['pension', 'benefits'],
            'prestation' => ['benefit'],
            'prestacione' => ['benefit'],
            'prestations' => ['benefit', 'benefits'],
            'prestacion' => ['benefit'],
            'prestaciones' => ['benefit', 'benefits'],
            'beneficio' => ['benefit'],
            'beneficios' => ['benefit', 'benefits'],
            'uitkering' => ['benefit'],
            'uitkeringen' => ['benefit', 'benefits'],
            'recouvrement' => ['collection', 'recovery'],
            'remboursement' => ['refund'],
            'retraite' => ['retirement', 'pension'],
            'salaire' => ['salary', 'wage'],
            'salaires' => ['salary', 'wages'],
            'solicitud' => ['claim', 'application', 'request'],
            'solicitude' => ['claim', 'application', 'request'],
            'solicitudes' => ['claim', 'claims', 'application', 'applications', 'request', 'requests'],
            'aanvraag' => ['claim', 'application', 'request'],
            'aanvragen' => ['claim', 'claims', 'application', 'applications', 'request', 'requests'],
        ];

        return array_values(array_unique(array_merge([$term], $dictionary[$term] ?? [])));
    }

    /**
     * @param Collection<int, string> $terms
     * @return Collection<int, string>
     */
    private function questionPhrases(string $question): Collection
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', Str::lower(Str::ascii($question)));

        $phrases = collect([
            'receivables management',
            'receivable management',
            'position budgeting',
            'position budgeting and control',
            'position budget',
            'employer registration',
            'employee registration',
            'id card',
            'id cards',
            'social security id',
            'benefit claim',
            'benefit claims',
            'contribution filing',
            'contribution filings',
            'employer contribution filing',
            'employer contribution filings',
            'employer self service',
            'employer self-service',
            'contribution calculation',
            'contribution calculations',
            'contribution rate',
            'contribution rates',
            'insurable earnings',
            'medical referee',
            'self service',
            'e services',
            'uitkeringsaanvraag',
            'uitkeringsaanvragen',
            'uitkering berekening',
            'bijdrage berekening',
            'premie berekening',
        ])->filter(fn (string $phrase) => str_contains($normalized, $phrase));

        $phrases = $phrases->merge($this->functionalPhrasesFromQuestion($normalized));

        if (
            (str_contains($normalized, 'cotisation') || str_contains($normalized, 'contribution') || str_contains($normalized, 'contribui') || str_contains($normalized, 'bijdrage') || str_contains($normalized, 'premie'))
            && (str_contains($normalized, 'calcul') || str_contains($normalized, 'bereken'))
        ) {
            $phrases = $phrases->merge(['contribution calculation', 'contribution rate', 'insurable earnings']);
        }

        if (
            (str_contains($normalized, 'benefit') || str_contains($normalized, 'prestacion') || str_contains($normalized, 'prestation') || str_contains($normalized, 'uitkering') || str_contains($normalized, 'pensioen'))
            && (str_contains($normalized, 'calcul') || str_contains($normalized, 'configur') || str_contains($normalized, 'bereken') || str_contains($normalized, 'ingesteld'))
        ) {
            $phrases = $phrases->merge(['benefit calculation', 'benefit claim', 'benefit policy', 'calculation method']);
        }

        return $phrases->unique()->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function functionalPhrasesFromQuestion(string $normalizedQuestion): Collection
    {
        preg_match_all(
            '/\b([a-z][a-z0-9]*(?:\s+[a-z][a-z0-9]*){0,3}\s+(?:management|setup|control|filing|calculation|processing|registration|payment|payments|receivables|payables))\b/',
            $normalizedQuestion,
            $matches
        );

        $noise = collect([
            'product management',
            'system management',
            'module management',
            'purpose management',
            'when management',
        ]);

        return collect($matches[1] ?? [])
            ->map(fn (string $phrase) => trim(preg_replace('/\s+/', ' ', $phrase) ?? ''))
            ->filter(fn (string $phrase) => strlen($phrase) >= 8 && ! $noise->contains($phrase))
            ->values();
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     * @return array{chunk: KnowledgeChunk, excerpt: string, score: int}
     */
    /**
     * @param array{business_areas: array<string, int>, intents: array<string, bool>} $profile
     */
    private function rankChunk(KnowledgeChunk $chunk, Collection $terms, Collection $phrases, array $profile): array
    {
        $title = $this->cleanText((string) $chunk->chunk_title);
        $text = $this->cleanText($chunk->chunk_text);
        $haystack = Str::lower($title . ' ' . $text);
        $score = 0;

        foreach ($terms as $term) {
            $termCount = substr_count($haystack, $term);

            if ($termCount === 0) {
                continue;
            }

            $score += min($termCount, 6) * 10;

            if (str_contains(Str::lower($title), $term)) {
                $score += 20;
            }
        }

        foreach ($phrases as $phrase) {
            if (str_contains($haystack, $phrase)) {
                $score += $phrase === 'medical referee' ? 260 : 160;
            }
        }

        if ($terms->count() > 1 && $terms->every(fn (string $term) => str_contains($haystack, $term))) {
            $score += 60;
        }

        $score += $this->conceptProfileScore($chunk, $haystack, $profile);
        $score += ((int) ($chunk->answer_priority ?? 50)) - 50;

        if ($this->prefersConfigurationAnswer($terms, $phrases)) {
            $score += match ($chunk->content_type) {
                'configuration' => 220,
                'business_logic' => 180,
                'glossary_definition' => 110,
                'screen_purpose' => 70,
                'workflow_outcome' => 40,
                'procedure_steps' => -140,
                default => 0,
            };
        } elseif (! $this->asksForProcedure($terms, $phrases)) {
            $score += match ($chunk->content_type) {
                'configuration' => 110,
                'business_logic' => 100,
                'glossary_definition' => 70,
                'screen_purpose' => 55,
                'procedure_steps' => -90,
                default => 0,
            };
        } elseif ($chunk->content_type === 'procedure_steps') {
            $score += 100;
        }

        if (str_contains($haystack, 'medical referee')) {
            if (str_contains($haystack, 'diagnosis') || str_contains($haystack, 'second doctor') || str_contains($haystack, 'healthcare provider')) {
                $score += 180;
            }

            if (str_contains($haystack, 'medical referee reason') || str_contains($haystack, 'medical referee reasons')) {
                $score -= 180;
            }
        }

        if ($this->isContributionCalculationIntent($terms, $phrases)) {
            foreach (['contribution rate', 'contribution rates', 'insurable earnings', 'contribution policy', 'contribution policies', 'contribution rules', 'calculation rules', 'employee group'] as $signal) {
                if (str_contains($haystack, $signal)) {
                    $score += 140;
                }
            }

            foreach (['configured', 'configuration', 'formula', 'formulas', 'percentage', 'percent', 'automatically display the social security contribution'] as $signal) {
                if (str_contains($haystack, $signal)) {
                    $score += 80;
                }
            }

            if (str_contains($haystack, 'benefit calculation') && ! str_contains($haystack, 'contribution rate')) {
                $score -= 180;
            }

            if (str_contains($haystack, 'adjustment') && ! str_contains($haystack, 'contribution rate')) {
                $score -= 120;
            }
        }

        if ($this->isBenefitCalculationIntent($terms, $phrases)) {
            $sourceTitle = Str::lower((string) ($chunk->sourceDocument?->title ?? ''));

            if (($chunk->business_area ?? '') === 'benefits') {
                $score += 180;
            }

            if (str_contains($sourceTitle . ' ' . $title, 'benefit')) {
                $score += 120;
            }

            foreach (['benefit policy', 'benefit policies', 'benefit class', 'benefit classes', 'benefit entitlement', 'entitlement calculation', 'benefit calculation', 'calculation method', 'rate table', 'eligibility', 'qualifying period'] as $signal) {
                if (str_contains($haystack, $signal)) {
                    $score += 170;
                }
            }

            foreach (['gross earning', 'taxable earning', 'net earning', 'insurable earnings', 'profile earnings', 'fixed amount', 'formula', 'parameters', 'applicable days', 'completed weeks'] as $signal) {
                if (str_contains($haystack, $signal)) {
                    $score += 100;
                }
            }

            if (str_contains($haystack, 'medical referee') && ! str_contains($haystack, 'benefit calculation')) {
                $score -= 180;
            }

            if (str_contains($haystack, 'delinquency') && ! str_contains($haystack, 'benefit calculation')) {
                $score -= 180;
            }

            if (str_contains($haystack, 'penalty') && ! str_contains($haystack, 'benefit calculation')) {
                $score -= 140;
            }

            if (str_contains($sourceTitle . ' ' . $title, 'penalt')) {
                $score -= 700;
            }

            if (($chunk->business_area ?? '') === 'contributions' && ! str_contains($haystack, 'benefit calculation') && ! str_contains($haystack, 'benefit policy')) {
                $score -= 300;
            }

            if (str_contains($haystack, 'contribution') && ! str_contains($haystack, 'benefit')) {
                $score -= 120;
            }
        }

        if ($this->isEmployerContributionFilingIntent($terms, $phrases)) {
            foreach (['employer contribution filing', 'contribution filing', 'contribution return', 'remittance', 'return period', 'filing period', 'employee contributions', 'employer contributions', 'self-service', 'self service', 'employer portal'] as $signal) {
                if (str_contains($haystack, $signal)) {
                    $score += 190;
                }
            }

            foreach (['rate', 'rates', 'insurable earning', 'insurable earnings', 'configured', 'configuration', 'period', 'submit', 'review', 'approve', 'approval', 'payment', 'receipt'] as $signal) {
                if (str_contains($haystack, $signal)) {
                    $score += 65;
                }
            }

            if (($chunk->business_area ?? '') === 'contributions') {
                $score += 170;
            }

            if (in_array($chunk->content_type, ['configuration', 'business_logic', 'screen_purpose', 'workflow_outcome', 'transaction_processing'], true)) {
                $score += 90;
            }

            foreach (['individual self service', 'self-employed', 'self employed', 'voluntary contributor', 'benefit claim', 'medical referee', 'delinquency', 'lawsuit', 'penalty'] as $negativeSignal) {
                if (str_contains($haystack, $negativeSignal)) {
                    $score -= 260;
                }
            }
        }

        if ($terms->contains('compliance') || $terms->contains('delinquency') || $terms->contains('arrears') || $terms->contains('penalty') || $terms->contains('audit')) {
            if (($chunk->business_area ?? '') === 'compliance') {
                $score += 260;
            }

            foreach (['compliance', 'delinquency', 'arrears', 'penalty', 'penalties', 'liabilities', 'audit', 'inspection', 'lawsuit', 'employer liable', 'employer liability'] as $signal) {
                if (str_contains($haystack, $signal)) {
                    $score += 90;
                }
            }

            if (($chunk->business_area ?? '') === 'benefits' && ! Str::contains($haystack, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit'])) {
                $score -= 500;
            }

            if (str_contains($haystack, 'medical referee') && ! Str::contains($haystack, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit'])) {
                $score -= 350;
            }
        }

        if (str_contains($haystack, 'form usage')) {
            $score += 120;
        }

        return [
            'chunk' => $chunk,
            'excerpt' => $this->bestExcerpt($text, $terms),
            'score' => $score,
        ];
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function isContributionCalculationIntent(Collection $terms, Collection $phrases): bool
    {
        return $terms->contains('contribution')
            && ($terms->contains('calculation') || $phrases->contains('contribution calculation'));
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function isBenefitCalculationIntent(Collection $terms, Collection $phrases): bool
    {
        return $terms->contains('benefit')
            && ($terms->contains('calculation') || $terms->contains('configuration') || $phrases->contains('benefit calculation'));
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function isEmployerContributionFilingIntent(Collection $terms, Collection $phrases): bool
    {
        $hasEmployer = $terms->contains('employer') || $phrases->contains('employer self service') || $phrases->contains('employer self-service');
        $hasContribution = $terms->contains('contribution') || $phrases->contains('contribution filing') || $phrases->contains('employer contribution filing');
        $hasFiling = $terms->contains('filing') || $terms->contains('declaration') || $terms->contains('remittance') || $phrases->contains('contribution filing') || $phrases->contains('employer contribution filing');

        return $hasEmployer && $hasContribution && $hasFiling;
    }

    /**
     * @return array{business_areas: array<string, int>, intents: array<string, bool>}
     */
    private function questionConceptProfile(string $question, Collection $terms, Collection $phrases): array
    {
        $questionForProfile = $this->benefitMeansAdvantage($question)
            ? preg_replace('/\b(key\s+)?benefits?\s+(of|from|for)\b/i', 'advantages of', $question)
            : $question;

        $normalized = Str::lower(Str::ascii($questionForProfile . ' ' . $terms->implode(' ') . ' ' . $phrases->implode(' ')));
        $areas = [
            'benefits' => ['benefit', 'benefits', 'claim', 'claims', 'claimant', 'pension', 'disability', 'maternity', 'sickness', 'survivor', 'entitlement', 'eligibility', 'medical referee'],
            'contributions' => ['contribution', 'contributions', 'filing', 'earnings', 'insurable earnings', 'contribution rate', 'employee group', 'employer due'],
            'registration' => ['registration', 'enrollment', 'registered', 'ssn', 'social security number', 'employer registration', 'employee registration', 'id card'],
            'payments' => ['payment', 'payments', 'receipt', 'cheque', 'check', 'bank file', 'payable', 'payment request'],
            'compliance' => ['compliance', 'delinquency', 'arrears', 'penalty', 'audit', 'inspection', 'lawsuit', 'legal action', 'enforcement'],
            'documents_letters' => ['letter', 'letters', 'template', 'certificate', 'document', 'notice', 'award letter', 'rejection letter'],
            'financials' => ['general ledger', 'gl ', 'accounting', 'invoice', 'receivable', 'payable', 'posting'],
            'general_setup' => ['configuration', 'configured', 'parameter', 'parameters', 'policy', 'policies', 'rule', 'rules', 'formula', 'rate', 'setup'],
        ];

        $businessAreas = collect($areas)
            ->map(fn (array $signals) => collect($signals)->sum(fn (string $signal) => str_contains($normalized, $signal) ? 1 : 0))
            ->filter(fn (int $score) => $score > 0)
            ->sortDesc()
            ->all();

        return [
            'business_areas' => $businessAreas,
            'intents' => [
                'configuration' => $this->prefersConfigurationAnswer($terms, $phrases),
                'calculation' => $terms->contains('calculation') || $phrases->contains(fn (string $phrase) => str_contains($phrase, 'calculation')),
                'procedure' => $this->asksForProcedure($terms, $phrases),
                'broad' => $this->isBroadQuestion($question),
            ],
        ];
    }

    /**
     * @param array{business_areas: array<string, int>, intents: array<string, bool>} $profile
     */
    private function conceptProfileScore(KnowledgeChunk $chunk, string $haystack, array $profile): int
    {
        $businessAreas = $profile['business_areas'];

        if ($businessAreas === []) {
            return 0;
        }

        $score = 0;
        $chunkArea = (string) ($chunk->business_area ?? 'general');
        $topArea = array_key_first($businessAreas);
        $topAreaScore = (int) ($businessAreas[$topArea] ?? 0);

        if (array_key_exists($chunkArea, $businessAreas)) {
            $score += 180 + min(160, ((int) $businessAreas[$chunkArea]) * 35);
        } elseif ($topAreaScore >= 2 && ! $this->chunkMentionsBusinessArea($haystack, $topArea)) {
            $score -= 160;
        }

        if (($profile['intents']['configuration'] ?? false) && $chunkArea === 'general_setup') {
            $score += 100;
        }

        if (($profile['intents']['procedure'] ?? false) && ($chunk->content_type ?? '') === 'procedure_steps') {
            $score += 120;
        }

        if (($profile['intents']['calculation'] ?? false) && Str::contains($haystack, ['calculation', 'calculated', 'formula', 'rate', 'method', 'eligibility', 'entitlement'])) {
            $score += 100;
        }

        return $score;
    }

    private function chunkMentionsBusinessArea(string $haystack, string $area): bool
    {
        $signals = [
            'benefits' => ['benefit', 'claim', 'claimant', 'pension', 'entitlement', 'eligibility'],
            'contributions' => ['contribution', 'filing', 'earnings', 'employer due'],
            'registration' => ['registration', 'ssn', 'id card', 'social security number'],
            'payments' => ['payment', 'receipt', 'cheque', 'bank file'],
            'compliance' => ['compliance', 'delinquency', 'penalty', 'audit', 'lawsuit'],
            'documents_letters' => ['letter', 'template', 'certificate', 'document', 'notice'],
            'financials' => ['general ledger', 'gl ', 'accounting', 'invoice'],
            'general_setup' => ['configuration', 'policy', 'parameter', 'setup'],
        ];

        return Str::contains($haystack, $signals[$area] ?? [$area]);
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function prefersConfigurationAnswer(Collection $terms, Collection $phrases): bool
    {
        return $terms->contains(fn (string $term) => in_array($term, ['calculation', 'calculate', 'configured', 'configuration', 'rule', 'policy', 'rate', 'formula', 'purpose'], true))
            || $phrases->contains(fn (string $phrase) => Str::contains($phrase, ['calculation', 'rate', 'insurable earnings', 'position budgeting']));
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function asksForProcedure(Collection $terms, Collection $phrases): bool
    {
        return $terms->contains(fn (string $term) => in_array($term, ['create', 'update', 'delete', 'enter', 'submit', 'approve', 'post', 'upload', 'click'], true))
            || $phrases->contains(fn (string $phrase) => Str::contains($phrase, ['how do i', 'how to', 'step']));
    }

    /**
     * @param Collection<int, string> $terms
     */
    private function bestExcerpt(string $text, Collection $terms): string
    {
        if ($terms->contains('position') && $terms->contains('budgeting') && str_contains($text, 'Position Budgeting and Control Budget Planning and Control')) {
            return 'The HRMS fact sheet module inventory lists Position Budgeting and Control as part of Interact HRMS.';
        }

        $sentences = collect(preg_split('/(?<=[.!?])\s+/', $text))
            ->map(fn (string $sentence) => trim($sentence))
            ->filter()
            ->values();

        if ($sentences->isEmpty()) {
            return Str::limit($text, 700);
        }

        $formUsageExcerpt = $this->formUsageExcerpt($text, $terms);

        if ($formUsageExcerpt !== null) {
            return $formUsageExcerpt;
        }

        $best = $sentences
            ->map(function (string $sentence) use ($terms) {
                $lower = Str::lower($sentence);
                $score = $terms->sum(fn (string $term) => substr_count($lower, $term));

                return ['sentence' => $sentence, 'score' => $score];
            })
            ->sortByDesc('score')
            ->first();

        $sentence = $best && $best['score'] > 0 ? $best['sentence'] : $sentences->first();

        return Str::limit($sentence, 700);
    }

    /**
     * @param Collection<int, string> $terms
     */
    private function formUsageExcerpt(string $text, Collection $terms): ?string
    {
        if (! preg_match('/\bForm\s+Usage\b[:\s-]*(.+?)(?=\b(?:Field|Fields|Buttons?|Tabs?|Steps?|Process|Navigation|Form\s+Fields|Screen|Notes?)\b[:\s-]|\z)/is', $text, $matches)) {
            return null;
        }

        $excerpt = trim(preg_replace('/\s+/', ' ', $matches[1] ?? ''));

        if ($excerpt === '') {
            return null;
        }

        $lower = Str::lower($excerpt);
        $hasRelevantTerm = $terms->isEmpty() || $terms->contains(fn (string $term) => str_contains($lower, $term));

        if (! $hasRelevantTerm) {
            return null;
        }

        return 'Form Usage: ' . Str::limit($excerpt, 900);
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function composeAnswer(string $question, Collection $matches, string $answerLanguage): string
    {
        $isYesNo = Str::startsWith(Str::lower(trim($question)), ['does ', 'do ', 'can ', 'is ', 'are ']);
        $topMatches = $matches->take(3);
        $productName = $topMatches->first()['chunk']->product?->name ?? 'the selected product';
        $citations = $topMatches
            ->map(fn (array $match) => $match['chunk']->citation_label ?: $match['chunk']->chunk_title)
            ->unique()
            ->implode('; ');

        $terms = $this->questionTerms($question);
        $phrases = $this->questionPhrases($question);

        if ($this->isEmployerContributionFilingIntent($terms, $phrases)) {
            return $this->composeEmployerContributionFilingAnswer($matches, $answerLanguage);
        }

        if ($this->isContributionCalculationIntent($terms, $phrases)) {
            return $this->composeContributionCalculationAnswer($matches, $answerLanguage);
        }

        if ($this->isBenefitCalculationIntent($terms, $phrases)) {
            return $this->composeBenefitCalculationAnswer($matches, $answerLanguage);
        }

        if ($this->isComplianceManagementIntent($terms, $phrases)) {
            return $this->composeComplianceManagementAnswer($matches, $answerLanguage);
        }

        if ($this->isReceivablesManagementIntent($terms, $phrases)) {
            return $this->composeReceivablesManagementAnswer($matches, $answerLanguage);
        }

        return $this->composeConciseAnswer($question, $matches, $productName, $answerLanguage);
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function isReceivablesManagementIntent(Collection $terms, Collection $phrases): bool
    {
        return $terms->contains(fn (string $term) => in_array($term, ['receivable', 'receivables', 'invoice', 'invoices'], true))
            || $phrases->contains(fn (string $phrase) => str_contains($phrase, 'receivable') || str_contains($phrase, 'receivables'));
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function isComplianceManagementIntent(Collection $terms, Collection $phrases): bool
    {
        return $terms->contains(fn (string $term) => in_array($term, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit', 'inspection', 'lawsuit'], true))
            || $phrases->contains(fn (string $phrase) => Str::contains($phrase, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit']));
    }

    /**
     * @param Collection<int, string> $terms
     * @param Collection<int, string> $phrases
     */
    private function shouldUseControlledAnswer(Collection $terms, Collection $phrases): bool
    {
        return $this->isComplianceManagementIntent($terms, $phrases);
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function composeComplianceManagementAnswer(Collection $matches, string $answerLanguage): string
    {
        $citations = $matches
            ->map(fn (array $match) => $match['chunk']->citation_label ?: $match['chunk']->chunk_title)
            ->unique()
            ->take(5)
            ->implode('; ');

        $messages = [
            'English' => [
                'lead' => 'In Interact SSAS, Compliance Management is used to control employer compliance, monitor liabilities, and follow up delinquency or arrears in a structured way.',
                'points' => [
                    'It helps the administration define and schedule compliance audits or inspections, including the audit scope, responsible officers, employers to be reviewed, and follow-up dates.',
                    'It gives users a controlled way to review employer liabilities, dues, delinquency, arrears, penalties, and related compliance cases instead of handling follow-up manually.',
                    'It supports audit visits and compliance follow-up by keeping the relevant employer, officer, period, findings, and status information together.',
                    'It improves management visibility because compliance work can be searched, tracked, reviewed, and reported as part of the SSAS workflow.',
                    'The practical value is better enforcement control, clearer audit accountability, and more consistent follow-up on employers that are late, underpaid, or otherwise non-compliant.',
                ],
                'sources' => 'Sources used',
            ],
            'French' => [
                'lead' => 'Dans Interact SSAS, Compliance Management sert a controler la conformite des employeurs, suivre les dettes et gerer les retards ou arrieres de maniere structuree.',
                'points' => [
                    'Il aide a definir et planifier les audits ou inspections, y compris le perimetre, les agents responsables, les employeurs concernes et les dates de suivi.',
                    'Il permet de suivre les dettes, arrieres, penalites et dossiers de conformite des employeurs dans un processus controle.',
                    'Il regroupe les informations relatives a l employeur, l agent, la periode, les constats et le statut de suivi.',
                    'Il ameliore la visibilite de gestion parce que les activites de conformite peuvent etre recherchees, suivies, revues et rapportees.',
                    'La valeur principale est un meilleur controle de l application des regles, une responsabilite d audit plus claire et un suivi plus coherent des employeurs non conformes.',
                ],
                'sources' => 'Sources utilisees',
            ],
        ];

        $message = $messages[$answerLanguage] ?? $messages['English'];
        $lines = [$message['lead']];

        foreach ($message['points'] as $point) {
            $lines[] = '- ' . $point;
        }

        if ($citations !== '') {
            $lines[] = '';
            $lines[] = $message['sources'] . ': ' . $citations . '.';
        }

        return implode("\n", $lines);
    }

    private function isContributionCalculationQuestion(string $question): bool
    {
        $question = Str::lower(Str::ascii($question));

        return (str_contains($question, 'cotisation') || str_contains($question, 'contribution') || str_contains($question, 'contribui'))
            && str_contains($question, 'calcul');
    }

    private function isBenefitCalculationQuestion(string $question): bool
    {
        $question = Str::lower(Str::ascii($question));

        return (str_contains($question, 'benefit') || str_contains($question, 'prestacion') || str_contains($question, 'prestation'))
            && (str_contains($question, 'calcul') || str_contains($question, 'configur'));
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function composeEmployerContributionFilingAnswer(Collection $matches, string $answerLanguage): string
    {
        $citations = $matches
            ->map(fn (array $match) => $match['chunk']->citation_label ?: $match['chunk']->chunk_title)
            ->unique()
            ->take(5)
            ->implode('; ');

        $messages = [
            'English' => [
                'lead' => 'In Interact SSAS, employer contribution filing through self-service is the employer-facing process for submitting contribution information for a contribution period.',
                'points' => [
                    'The filing is driven by the employer record, the applicable contribution period, employee or member details, insurable earnings, and the configured contribution rules and rates.',
                    'Through the employer self-service channel, the employer can prepare or submit the filing data instead of relying only on back-office entry.',
                    'The submitted filing gives the administration the basis to calculate or validate employer and employee contribution amounts, review exceptions, and determine the amount due.',
                    'After submission, the filing can move through configured review, approval, payment, receipt, posting, adjustment, and reporting processes.',
                    'So the main purpose is not generic self-service; it is controlled contribution declaration by the employer, with self-service acting as the channel for capturing and submitting the filing.',
                ],
                'sources' => 'Sources used',
            ],
            'French' => [
                'lead' => 'Dans Interact SSAS, la declaration des cotisations par l employeur via le self-service est le processus par lequel l employeur soumet les informations de cotisation pour une periode de cotisation.',
                'points' => [
                    'La declaration repose sur le dossier employeur, la periode applicable, les donnees des employes ou membres, les revenus assurables, ainsi que les regles et taux de cotisation configures.',
                    'Le portail self-service employeur permet a l employeur de preparer ou soumettre les donnees de declaration sans dependance exclusive a la saisie back-office.',
                    'La declaration soumise permet a l administration de calculer ou verifier les cotisations employeur et employe, traiter les exceptions et determiner le montant du.',
                    'Apres soumission, la declaration peut suivre les processus configures de revue, approbation, paiement, recu, comptabilisation, ajustement et reporting.',
                    'Le point principal n est donc pas le self-service en general, mais la declaration controlee des cotisations par l employeur, le self-service etant le canal de saisie et de soumission.',
                ],
                'sources' => 'Sources utilisees',
            ],
            'Spanish' => [
                'lead' => 'En Interact SSAS, la presentacion de contribuciones del empleador por autoservicio es el proceso mediante el cual el empleador remite la informacion de contribuciones para un periodo de contribucion.',
                'points' => [
                    'La presentacion se basa en el registro del empleador, el periodo aplicable, los datos de empleados o miembros, los ingresos asegurables y las reglas y tasas de contribucion configuradas.',
                    'El canal de autoservicio del empleador permite preparar o enviar los datos sin depender solamente de la captura interna por la administracion.',
                    'La presentacion enviada da base para calcular o validar las contribuciones del empleador y del empleado, revisar excepciones y determinar el importe adeudado.',
                    'Luego puede pasar por los procesos configurados de revision, aprobacion, pago, recibo, contabilizacion, ajuste e informes.',
                    'Por tanto, el foco no es el autoservicio en general, sino la declaracion controlada de contribuciones por el empleador, usando autoservicio como canal de captura y envio.',
                ],
                'sources' => 'Fuentes utilizadas',
            ],
            'Portuguese' => [
                'lead' => 'No Interact SSAS, a declaracao de contribuicoes do empregador via autosservico e o processo pelo qual o empregador envia as informacoes de contribuicao para um periodo de contribuicao.',
                'points' => [
                    'A declaracao e orientada pelo registro do empregador, o periodo aplicavel, os dados de empregados ou membros, os rendimentos seguraveis e as regras e taxas de contribuicao configuradas.',
                    'Pelo canal de autosservico do empregador, o empregador pode preparar ou enviar os dados sem depender apenas da digitacao interna da administracao.',
                    'A declaracao enviada serve de base para calcular ou validar as contribuicoes do empregador e do empregado, analisar excecoes e determinar o valor devido.',
                    'Depois da submissao, a declaracao pode seguir processos configurados de revisao, aprovacao, pagamento, recibo, contabilizacao, ajuste e relatorios.',
                    'Assim, o foco principal nao e o autosservico em geral, mas a declaracao controlada de contribuicoes pelo empregador, com autosservico como canal de captura e envio.',
                ],
                'sources' => 'Fontes utilizadas',
            ],
            'Dutch' => [
                'lead' => 'In Interact SSAS is werkgeversaangifte van bijdragen via self-service het proces waarmee een werkgever bijdragegegevens voor een bijdrageperiode indient.',
                'points' => [
                    'De aangifte wordt bepaald door het werkgeversdossier, de toepasselijke periode, werknemer- of ledengegevens, verzekerbare inkomsten en de ingestelde bijdrage regels en percentages.',
                    'Via het werkgevers-self-service kanaal kan de werkgever de aangiftegegevens voorbereiden of indienen zonder uitsluitend afhankelijk te zijn van back-office invoer.',
                    'De ingediende aangifte vormt de basis om werkgevers- en werknemersbijdragen te berekenen of te valideren, uitzonderingen te beoordelen en het verschuldigde bedrag te bepalen.',
                    'Na indiening kan de aangifte door ingestelde processen gaan voor beoordeling, goedkeuring, betaling, ontvangstbewijs, boeking, correctie en rapportage.',
                    'De kern is dus niet algemene self-service, maar gecontroleerde bijdrageaangifte door de werkgever, waarbij self-service het kanaal is voor vastlegging en indiening.',
                ],
                'sources' => 'Gebruikte bronnen',
            ],
        ];

        $message = $messages[$answerLanguage] ?? $messages['English'];
        $lines = [$message['lead']];

        foreach ($message['points'] as $point) {
            $lines[] = '- ' . $point;
        }

        if ($citations !== '') {
            $lines[] = '';
            $lines[] = $message['sources'] . ': ' . $citations . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function composeContributionCalculationAnswer(Collection $matches, string $answerLanguage): string
    {
        $citations = $matches
            ->map(fn (array $match) => $match['chunk']->citation_label ?: $match['chunk']->chunk_title)
            ->unique()
            ->take(5)
            ->implode('; ');

        $messages = [
            'English' => [
                'lead' => 'Interact SSAS calculates contributions by applying configured contribution rates, rules, and formulas to the applicable insurable earnings or contribution base.',
                'points' => [
                    'The configuration can define employer and employee rates, employee groups, contribution types, effective dates, ceilings, exemptions, and special rules.',
                    'For each relevant filing or assessment, the system uses those settings to determine the employer due, employee due, and total contribution payable.',
                    'Different rules can apply for employers, employees, self-employed persons, voluntary contributors, or other configured groups.',
                    'The calculated contribution amounts then drive the filing, liability, payment, posting, and reporting outcomes.',
                ],
                'sources' => 'Sources used',
            ],
            'French' => [
                'lead' => 'Interact SSAS calcule les cotisations en appliquant les taux, regles et formules de cotisation configures a la remuneration assurable applicable ou a la base de cotisation.',
                'points' => [
                    'La configuration peut definir les taux employeur et employe, les groupes d employes, les types de cotisation, les dates d effet, les plafonds, les exemptions et les regles particulieres.',
                    'Pour chaque declaration ou evaluation concernee, le systeme utilise ces parametres pour determiner la cotisation due par l employeur, la cotisation due par l employe et le total payable.',
                    'Des regles differentes peuvent s appliquer aux employeurs, employes, travailleurs independants, cotisants volontaires ou autres groupes configures.',
                    'Les montants calcules alimentent ensuite les resultats de declaration, de dette, de paiement, de comptabilisation et de reporting.',
                ],
                'sources' => 'Sources utilisees',
            ],
            'Spanish' => [
                'lead' => 'Interact SSAS calcula las contribuciones aplicando tasas, reglas y formulas de contribucion configuradas a los ingresos asegurables aplicables o a la base de contribucion.',
                'points' => [
                    'La configuracion puede definir tasas del empleador y del empleado, grupos de empleados, tipos de contribucion, fechas efectivas, topes, exenciones y reglas especiales.',
                    'Para cada declaracion o evaluacion relevante, el sistema usa esos parametros para determinar la contribucion del empleador, la contribucion del empleado y el total pagadero.',
                    'Pueden aplicarse reglas diferentes para empleadores, empleados, trabajadores independientes, contribuyentes voluntarios u otros grupos configurados.',
                    'Los montos calculados alimentan los resultados de declaracion, obligacion, pago, contabilizacion e informes.',
                ],
                'sources' => 'Fuentes utilizadas',
            ],
            'Portuguese' => [
                'lead' => 'Interact SSAS calcula as contribuicoes aplicando taxas, regras e formulas de contribuicao configuradas aos rendimentos seguraveis aplicaveis ou a base de contribuicao.',
                'points' => [
                    'A configuracao pode definir taxas do empregador e do empregado, grupos de empregados, tipos de contribuicao, datas de vigencia, tetos, isencoes e regras especiais.',
                    'Para cada declaracao ou avaliacao relevante, o sistema usa esses parametros para determinar a contribuicao do empregador, a contribuicao do empregado e o total a pagar.',
                    'Regras diferentes podem ser aplicadas a empregadores, empregados, trabalhadores independentes, contribuintes voluntarios ou outros grupos configurados.',
                    'Os valores calculados alimentam os resultados de declaracao, obrigacao, pagamento, contabilizacao e relatorios.',
                ],
                'sources' => 'Fontes utilizadas',
            ],
            'Dutch' => [
                'lead' => 'Interact SSAS berekent bijdragen door ingestelde bijdragepercentages, regels en formules toe te passen op de toepasselijke verzekerbare inkomsten of bijdragebasis.',
                'points' => [
                    'De configuratie kan werkgevers- en werknemerspercentages, werknemersgroepen, bijdrage types, ingangsdatums, plafonds, vrijstellingen en speciale regels bepalen.',
                    'Voor elke relevante aangifte of beoordeling gebruikt het systeem deze instellingen om het werkgeversdeel, werknemersdeel en het totale te betalen bedrag te bepalen.',
                    'Verschillende regels kunnen gelden voor werkgevers, werknemers, zelfstandigen, vrijwillige bijdragers of andere ingestelde groepen.',
                    'De berekende bedragen sturen daarna aangifte, verplichting, betaling, boeking en rapportage aan.',
                ],
                'sources' => 'Gebruikte bronnen',
            ],
            'Arabic' => [
                'lead' => 'يحسب Interact SSAS الاشتراكات من خلال تطبيق معدلات وقواعد وصيغ الاشتراك المكوّنة على الأرباح القابلة للتأمين أو على أساس الاشتراك المعمول به.',
                'points' => [
                    'يمكن أن تحدد الإعدادات معدلات صاحب العمل والموظف، ومجموعات الموظفين، وأنواع الاشتراك، وتواريخ النفاذ، والسقوف، والإعفاءات، والقواعد الخاصة.',
                    'لكل إقرار أو تقييم ذي صلة، يستخدم النظام هذه الإعدادات لتحديد اشتراك صاحب العمل، واشتراك الموظف، وإجمالي الاشتراك المستحق.',
                    'يمكن تطبيق قواعد مختلفة على أصحاب العمل، والموظفين، والعاملين لحسابهم الخاص، والمشتركين الطوعيين، أو أي مجموعات أخرى مكوّنة.',
                    'تغذي مبالغ الاشتراك المحسوبة نتائج الإقرار، والالتزام، والدفع، والترحيل، والتقارير.',
                ],
                'sources' => 'المصادر المستخدمة',
            ],
        ];

        $message = $messages[$answerLanguage] ?? $messages['English'];
        $lines = [$message['lead']];

        foreach ($message['points'] as $point) {
            $lines[] = '- ' . $point;
        }

        if ($citations !== '') {
            $lines[] = '';
            $lines[] = $message['sources'] . ': ' . $citations . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function composeReceivablesManagementAnswer(Collection $matches, string $answerLanguage): string
    {
        $citations = $matches
            ->filter(fn (array $match) => Str::contains(Str::lower($match['chunk']->chunk_text . ' ' . $match['chunk']->chunk_title), ['receivable', 'invoice']))
            ->map(fn (array $match) => $match['chunk']->citation_label ?: $match['chunk']->chunk_title)
            ->unique()
            ->take(5)
            ->implode('; ');

        $messages = [
            'English' => [
                'lead' => 'In Interact SSAS, Receivables Management is used to control invoices and amounts that need to be collected from external parties.',
                'points' => [
                    'Use it when the administration needs to create, track, edit, cancel, or follow up on invoices rather than process a standard contribution filing or benefit payment.',
                    'The general setup controls invoice numbering, yearly prefixes, number recycling, invoice types, payment terms, and late-payment interest settings.',
                    'Invoice-related setup also covers service items and cancellation reasons, so the receivable can be classified and controlled consistently.',
                    'Operationally, the receivables screen gives users access to invoice lists, filtering, creation, updates, and related follow-up for amounts owed.',
                ],
                'sources' => 'Sources used',
            ],
            'French' => [
                'lead' => 'Dans Interact SSAS, Receivables Management sert a controler les factures et les montants a recouvrer aupres de parties externes.',
                'points' => [
                    'Il s utilise lorsque l administration doit creer, suivre, modifier, annuler ou relancer des factures, plutot que traiter une declaration de cotisations ou un paiement de prestation standard.',
                    'Le parametrage general controle la numerotation des factures, les prefixes annuels, le recyclage des numeros, les types de facture, les conditions de paiement et les interets de retard.',
                    'Le parametrage lie aux factures couvre aussi les articles de service et les raisons d annulation, afin de classifier et controler la creance de maniere coherente.',
                    'Sur le plan operationnel, l ecran des creances donne acces aux listes de factures, aux filtres, a la creation, aux mises a jour et au suivi des montants dus.',
                ],
                'sources' => 'Sources utilisees',
            ],
            'Spanish' => [
                'lead' => 'En Interact SSAS, Receivables Management se utiliza para controlar facturas y montos que deben cobrarse a partes externas.',
                'points' => [
                    'Se usa cuando la administracion necesita crear, seguir, editar, cancelar o dar seguimiento a facturas, en lugar de procesar una declaracion normal de contribuciones o un pago de beneficios.',
                    'La configuracion general controla la numeracion de facturas, prefijos anuales, reciclaje de numeros, tipos de factura, terminos de pago e intereses por mora.',
                    'La configuracion de facturas tambien cubre articulos de servicio y motivos de cancelacion, para clasificar y controlar la cuenta por cobrar de forma consistente.',
                    'Operativamente, la pantalla de cuentas por cobrar permite acceder a listas de facturas, filtros, creacion, actualizaciones y seguimiento de montos adeudados.',
                ],
                'sources' => 'Fuentes utilizadas',
            ],
            'Portuguese' => [
                'lead' => 'No Interact SSAS, Receivables Management e usado para controlar faturas e valores a receber de partes externas.',
                'points' => [
                    'Use quando a administracao precisa criar, acompanhar, editar, cancelar ou dar seguimento a faturas, em vez de processar uma declaracao normal de contribuicoes ou um pagamento de beneficios.',
                    'A configuracao geral controla numeracao de faturas, prefixos anuais, reciclagem de numeros, tipos de fatura, prazos de pagamento e juros por atraso.',
                    'A configuracao de faturas tambem cobre itens de servico e motivos de cancelamento, para classificar e controlar o valor a receber de forma consistente.',
                    'Operacionalmente, a tela de contas a receber da acesso a listas de faturas, filtros, criacao, atualizacoes e acompanhamento de valores devidos.',
                ],
                'sources' => 'Fontes utilizadas',
            ],
            'Dutch' => [
                'lead' => 'In Interact SSAS wordt Receivables Management gebruikt om facturen en te innen bedragen van externe partijen te beheren.',
                'points' => [
                    'Gebruik het wanneer de administratie facturen moet aanmaken, volgen, wijzigen, annuleren of opvolgen, in plaats van een normale bijdrageaangifte of uitkeringsbetaling te verwerken.',
                    'De algemene instellingen bepalen factuurnummering, jaarprefixen, hergebruik van nummers, factuurtypes, betalingstermijnen en rente bij late betaling.',
                    'Factuurinstellingen omvatten ook service-items en annuleringsredenen, zodat de vordering consistent kan worden geclassificeerd en beheerd.',
                    'Operationeel geeft het scherm toegang tot factuurlijsten, filters, aanmaak, updates en opvolging van verschuldigde bedragen.',
                ],
                'sources' => 'Gebruikte bronnen',
            ],
        ];

        $message = $messages[$answerLanguage] ?? $messages['English'];
        $lines = [$message['lead']];

        foreach ($message['points'] as $point) {
            $lines[] = '- ' . $point;
        }

        if ($citations !== '') {
            $lines[] = '';
            $lines[] = $message['sources'] . ': ' . $citations . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function composeBenefitCalculationAnswer(Collection $matches, string $answerLanguage): string
    {
        $citations = $matches
            ->map(fn (array $match) => $match['chunk']->citation_label ?: $match['chunk']->chunk_title)
            ->unique()
            ->take(5)
            ->implode('; ');

        $messages = [
            'English' => [
                'lead' => 'Interact SSAS calculates benefit claims by applying configured benefit policies, eligibility rules, entitlement rules, calculation methods, formulas, and rate tables to the claimant and claim data.',
                'points' => [
                    'The configuration can define benefit classes, qualifying rules, calculation bases, formulas, rates, limits, applicable days or weeks, earning types, and other benefit-specific parameters.',
                    'Those settings determine whether a claimant is eligible, what entitlement applies, which calculation method is used, and how the benefit amount is derived.',
                    'The calculated result then drives the claim decision, approvals, award or rejection letters, payment processing, accounting entries, suspensions, adjustments, and reporting outcomes.',
                    'Different benefit types can therefore behave differently because their policies, formulas, limits, supporting evidence, review rules, and payment rules can be configured separately.',
                ],
                'sources' => 'Sources used',
            ],
            'French' => [
                'lead' => 'Interact SSAS calcule les demandes de prestations en appliquant les politiques de prestations, les regles d eligibilite, les regles de droit, les methodes de calcul, les formules et les tables de taux configurees aux donnees du demandeur et de la demande.',
                'points' => [
                    'La configuration peut definir les classes de prestations, les conditions d admissibilite, les bases de calcul, les formules, les taux, les limites, les jours ou semaines applicables, les types de revenus et d autres parametres propres a la prestation.',
                    'Ces parametres determinent si le demandeur est eligible, quel droit s applique, quelle methode de calcul est utilisee et comment le montant de la prestation est derive.',
                    'Le resultat calcule alimente ensuite la decision, les approbations, les lettres d attribution ou de rejet, le traitement des paiements, les ecritures comptables, les suspensions, les ajustements et les rapports.',
                    'Les differents types de prestations peuvent donc se comporter differemment parce que leurs politiques, formules, limites, justificatifs, regles de revue et regles de paiement peuvent etre configures separement.',
                ],
                'sources' => 'Sources utilisees',
            ],
            'Spanish' => [
                'lead' => 'Interact SSAS calcula las solicitudes de prestaciones aplicando politicas de beneficios configuradas, reglas de elegibilidad, reglas de derecho, metodos de calculo, formulas y tablas de tasas a los datos del solicitante y de la solicitud.',
                'points' => [
                    'La configuracion puede definir clases de beneficios, reglas de calificacion, bases de calculo, formulas, tasas, limites, dias o semanas aplicables, tipos de ingresos y otros parametros especificos de cada beneficio.',
                    'Esos parametros determinan si el solicitante es elegible, que derecho aplica, que metodo de calculo se usa y como se obtiene el monto de la prestacion.',
                    'El resultado calculado alimenta despues la decision de la solicitud, aprobaciones, cartas de otorgamiento o rechazo, procesamiento de pagos, asientos contables, suspensiones, ajustes e informes.',
                    'Distintos tipos de beneficios pueden comportarse de manera diferente porque sus politicas, formulas, limites, evidencias requeridas, reglas de revision y reglas de pago pueden configurarse por separado.',
                ],
                'sources' => 'Fuentes utilizadas',
            ],
            'Portuguese' => [
                'lead' => 'Interact SSAS calcula as solicitacoes de beneficios aplicando politicas de beneficios configuradas, regras de elegibilidade, regras de direito, metodos de calculo, formulas e tabelas de taxas aos dados do requerente e da solicitacao.',
                'points' => [
                    'A configuracao pode definir classes de beneficios, regras de qualificacao, bases de calculo, formulas, taxas, limites, dias ou semanas aplicaveis, tipos de rendimento e outros parametros especificos de cada beneficio.',
                    'Esses parametros determinam se o requerente e elegivel, qual direito se aplica, qual metodo de calculo e usado e como o valor do beneficio e derivado.',
                    'O resultado calculado alimenta depois a decisao da solicitacao, aprovacoes, cartas de concessao ou rejeicao, processamento de pagamentos, lancamentos contabilisticos, suspensoes, ajustes e relatorios.',
                    'Diferentes tipos de beneficios podem comportar-se de forma diferente porque suas politicas, formulas, limites, evidencias exigidas, regras de revisao e regras de pagamento podem ser configuradas separadamente.',
                ],
                'sources' => 'Fontes utilizadas',
            ],
            'Dutch' => [
                'lead' => 'Interact SSAS berekent uitkeringsaanvragen door ingestelde uitkeringspolicies, geschiktheidsregels, aanspraakregels, berekeningsmethoden, formules en tarieftabellen toe te passen op de gegevens van de aanvrager en de aanvraag.',
                'points' => [
                    'De configuratie kan uitkeringsklassen, kwalificatieregels, berekeningsbases, formules, tarieven, limieten, toepasselijke dagen of weken, inkomenssoorten en andere uitkeringsspecifieke parameters bepalen.',
                    'Deze instellingen bepalen of de aanvrager in aanmerking komt, welk recht van toepassing is, welke berekeningsmethode wordt gebruikt en hoe het uitkeringsbedrag wordt afgeleid.',
                    'Het berekende resultaat stuurt daarna de aanvraagbeslissing, goedkeuringen, toekennings- of afwijzingsbrieven, betalingsverwerking, boekingen, opschortingen, correcties en rapportages aan.',
                    'Verschillende uitkeringstypes kunnen dus anders werken omdat hun policies, formules, limieten, bewijsstukken, beoordelingsregels en betalingsregels afzonderlijk kunnen worden ingesteld.',
                ],
                'sources' => 'Gebruikte bronnen',
            ],
            'Arabic' => [
                'lead' => 'Interact SSAS calculates benefit claims by applying configured benefit policies, eligibility rules, entitlement rules, calculation methods, formulas, and rate tables to the claimant and claim data.',
                'points' => [
                    'Configuration can define benefit classes, qualifying rules, calculation bases, formulas, rates, limits, applicable days or weeks, earning types, and other benefit-specific parameters.',
                    'Those settings determine eligibility, entitlement, the calculation method, and the derived benefit amount.',
                    'The calculated result drives claim decisions, approvals, award or rejection letters, payments, accounting, suspensions, adjustments, and reporting.',
                    'Different benefit types can behave differently because their policies, formulas, limits, evidence requirements, review rules, and payment rules can be configured separately.',
                ],
                'sources' => 'Sources used',
            ],
        ];

        $message = $messages[$answerLanguage] ?? $messages['English'];
        $lines = [$message['lead']];

        foreach ($message['points'] as $point) {
            $lines[] = '- ' . $point;
        }

        if ($citations !== '') {
            $lines[] = '';
            $lines[] = $message['sources'] . ': ' . $citations . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function composeConciseAnswer(string $question, Collection $matches, string $productName, string $answerLanguage): string
    {
        $isYesNo = Str::startsWith(Str::lower(trim($question)), ['does ', 'do ', 'can ', 'is ', 'are ']);
        $topic = $this->topicLabel($question);
        $citations = $matches
            ->map(fn (array $match) => $match['chunk']->citation_label ?: $match['chunk']->chunk_title)
            ->unique()
            ->take(5)
            ->implode('; ');
        $points = $this->answerPoints($matches, 5);

        $lines = [
            $isYesNo
                ? $this->localizedMessage('yes_supports', $answerLanguage, ['product' => $productName, 'topic' => $topic])
                : $this->localizedMessage('supports_intro', $answerLanguage, ['product' => $productName, 'topic' => $topic]),
        ];

        foreach ($points as $point) {
            $lines[] = '- ' . $point;
        }

        if ($citations !== '') {
            $lines[] = '';
            $lines[] = $this->localizedMessage('sources_used', $answerLanguage) . ': ' . $citations . '.';
        }

        $lines[] = '';
        $lines[] = $this->localizedMessage('grounded_relevant', $answerLanguage);

        return implode("\n", $lines);
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     * @return array<int, string>
     */
    private function answerPoints(Collection $matches, int $limit = 5): array
    {
        $points = $matches
            ->take($limit)
            ->map(fn (array $match) => $this->sentenceFromExcerpt($match['excerpt']))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_slice(array_values(array_unique($points)), 0, $limit);
    }

    private function sentenceFromExcerpt(string $excerpt): string
    {
        $excerpt = $this->cleanAnswerText($excerpt);
        $sentences = collect(preg_split('/(?<=[.!?])\s+/', $excerpt))
            ->map(fn (string $sentence) => trim($sentence))
            ->filter(fn (string $sentence) => strlen($sentence) > 30)
            ->values();
        $sentence = $sentences->first() ?: $excerpt;

        return Str::finish(Str::limit($sentence, 220), '.');
    }

    private function topicLabel(string $question): string
    {
        $question = Str::lower(trim($question));

        foreach (['position budgeting and control', 'position budgeting', 'payroll budgeting', 'budget planning and control'] as $topic) {
            if (str_contains($question, $topic)) {
                return Str::title($topic);
            }
        }

        $question = preg_replace('/^(tell me about|explain(?:\s+to\s+me)?|describe|what is|what are|does|do|can|is|are|provide|give me|how does|how do|how can|how)\s+/i', '', $question) ?? $question;
        $question = preg_replace('/\s+in\s+interact\s+(ssas|hrms|ebpc|erms).*$/i', '', $question) ?? $question;
        $question = preg_replace('/\s+through\s+(the\s+)?following\s+capabilities\s*$/i', '', $question) ?? $question;
        $question = preg_replace('/[?!¿¡]+/', '', $question) ?? $question;
        $question = preg_replace('/\s+/', ' ', $question) ?? $question;
        $question = trim($question, " \t\n\r\0\x0B:;,.\"'");

        if ($question === '' || $question === null) {
            return 'the requested topic';
        }

        return $question;
    }

    /**
     * @param Collection<int, array{chunk: KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function humanSummarySentence(Collection $matches): string
    {
        $combined = Str::lower($matches->pluck('excerpt')->implode(' '));
        $points = [];

        if (str_contains($combined, 'registration')) {
            $points[] = 'registration workflows for individuals, employers, and related entities';
        }

        if (str_contains($combined, 'id card') || str_contains($combined, 'id cards')) {
            $points[] = 'social security ID card issuance and replacement-related processing';
        }

        if (str_contains($combined, 'social security number')) {
            $points[] = 'social security number application and identification processes';
        }

        if (str_contains($combined, 'employer')) {
            $points[] = 'employer-facing records and transactions';
        }

        if (str_contains($combined, 'benefit')) {
            $points[] = 'benefit-related claims or payment processing';
        }

        if (str_contains($combined, 'contribution')) {
            $points[] = 'contribution filing, payment, and adjustment processes';
        }

        $points = collect($points)->unique()->values();

        if ($points->isEmpty()) {
            return Str::finish(Str::limit($matches->first()['excerpt'] ?? '', 420), '.');
        }

        if ($points->count() === 1) {
            return 'The relevant material specifically points to ' . $points->first() . '.';
        }

        return 'The relevant material specifically points to ' . $points->slice(0, -1)->implode(', ') . ', and ' . $points->last() . '.';
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{00AD}", "\u{00FF}", '??'], ' ', $text);
        $text = str_replace(['?ex', '?ow', 'Uni?ed', 'uni?ed', 'Bene?ts', 'bene?ts', 'Certi?cates'], ['flex', 'flow', 'Unified', 'unified', 'Benefits', 'benefits', 'Certificates'], $text);
        $text = str_replace(['stafing', 'Stafing', 'diferent', 'Diferent', 'efective', 'Efective'], ['staffing', 'Staffing', 'different', 'Different', 'effective', 'Effective'], $text);
        $text = str_replace(['atendance', 'Atendance', 'eficient', 'Eficient', 'eficiency', 'Eficiency', 'ofers', 'Ofers', 'ofice', 'Ofice', 'leters', 'Leters', 'staf'], ['attendance', 'Attendance', 'efficient', 'Efficient', 'efficiency', 'Efficiency', 'offers', 'Offers', 'office', 'Office', 'letters', 'Letters', 'staff'], $text);
        $text = str_replace(['stafffing', 'Stafffing'], ['staffing', 'Staffing'], $text);
        $text = preg_replace('/\bself\s+employed\b/i', 'self-employed', $text);
        $text = preg_replace('/\bself\s+employment\b/i', 'self-employment', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($this->normalizeProductAcronyms($text ?? ''));
    }

    private function cleanAnswerText(string $text): string
    {
        $text = $this->cleanText($text);
        $text = preg_replace('/\s*>{2,}\s*/', ' > ', $text);
        $text = preg_replace('/(?:^|\s)[?•]\s*/u', ' ', $text);
        $text = preg_replace('/\bFIG\s*URE\s*\d+[^.]*\)/i', ' ', $text);
        $text = preg_replace('/\bGo to\s+[^.]{0,180}?(?=\.|$)/i', ' ', $text);
        $text = preg_replace('/\b(click|select|enter|choose)\b[^.]{0,160}\./i', ' ', $text);
        $text = str_replace(['>>>', '>>', ' - ', ' : -'], [' > ', ' > ', ' ', ':'], $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($this->normalizeProductAcronyms($text ?? ''));
    }

    private function normalizeProductAcronyms(string $text): string
    {
        $text = preg_replace('/\bSsas\b/i', 'SSAS', $text) ?? $text;
        $text = preg_replace('/\bHrms\b/i', 'HRMS', $text) ?? $text;
        $text = preg_replace('/\bErms\b/i', 'ERMS', $text) ?? $text;
        $text = preg_replace('/\bEbpc\b/i', 'EBPC', $text) ?? $text;

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private function hrmsModuleInventory(): array
    {
        return [
            'Position Budgeting and Control',
            'Budget Planning and Control',
            'Payroll Budgeting and Control',
            'Recruitment Management',
            'Contract and Hiring Management',
            'Background Screening',
            'Onboarding Management',
            'Probationary Period Management',
            'Employee Badge Printing',
            'Benefit Planning and Enrollment',
            'Pension Fund Management',
            'Investment Fund Management',
            'Indemnity Payments Management',
            'COBRA Management',
            'PTO/Leave Management',
            'Leave Planner',
            'Time and Attendance Management',
            'Biometric Clock and Access Control',
            'Resources Scheduling',
            'Software Clock Management',
            'FMLA Management',
            'Job Classification',
            'Competency Management',
            'Training Management',
            'Training Evaluation',
            'Performance Management',
            'Career Planning',
            'Succession Planning',
            'Progress Reporting',
            'Organization Management',
            'Unified Employee Electronic Record',
            'Sticky Notes Management',
            'HR Actions Management',
            'Disciplinary Actions Management',
            'Policy Publishing',
            'Passport and Visa Tracking',
            'Housing and Accommodation',
            'Travel Management',
            'Employee Asset Management',
            'Parking Space Planning and Management',
            'Office Space Planning and Management',
            'Risk Management',
            'Health and Safety Management',
            'Letters and Certificates Management',
            'Supervisors and Managers Management',
            'Suggestion Box',
            'Offboarding Management',
            'Employee Alarm Management',
            'System Manager',
            'Web Services',
            'Alerts Management',
            'Workflow Management',
            'Active Directory Integration',
            'Email Management',
            'Chat Channel Management',
            'Helpdesk Management',
            'Mass Updates',
            'Grants Management',
            'Medical Residency Management',
            'HSE Management',
            'KPI Dashboard',
            'Reports Management',
            'Compensation Management',
            'Payroll Management',
            'Payroll Wizard',
            'Expense Management',
            'Labor Costing and Billing Management',
            'Garnishment Management',
            'Loan Management',
            'Commission Management',
            'Third-Party Payments',
            'Employee Self-Service',
            'Organization Unit Self-Service',
            'Applicant Self-Service',
            'External Recruiter Self-Service',
            'Client Self-Service',
        ];
    }
}
