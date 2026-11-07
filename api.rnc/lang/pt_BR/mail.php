<?php

return [
    'common' => [
        'code_label' => 'Seu código',
        'footer_reason' => 'Você recebeu isto porque alguém usou este endereço para criar uma conta.',
    ],

    'verification' => [
        'subject' => 'Seu código de verificação',
        'preheader' => 'O código vale por :minutes minutos.',
        'heading' => 'Confirme seu e-mail',
        'body' => 'Use o código abaixo para terminar de criar sua conta.',
        'expires' => 'O código vale por :minutes minutos.',
        'ignore' => 'Se você não pediu uma conta, pode ignorar esta mensagem.',
    ],

    'meeting' => [
        'footer_reason' => 'Você recebeu este e-mail porque agendou uma reunião no diagnóstico da Reforma Tributária.',
        'when_label' => 'Quando',
        'with_label' => 'Com',
        'length_label' => 'Duração',
        'length' => ':minutes minutos, online ou no nosso escritório',
        'found' => 'No diagnóstico da :company encontramos :opportunities e :risks. Na reunião o especialista abre o detalhe de cada item e o plano de ação.',
        'opportunity' => '{1} 1 oportunidade|[2,*] :count oportunidades|[0] nenhuma oportunidade',
        'risk' => '{1} 1 risco|[2,*] :count riscos|[0] nenhum risco',
        'reschedule' => 'Precisa mudar o horário? Responda este e-mail ou ligue :phone que a equipe remarca.',
        'reschedule_no_phone' => 'Precisa mudar o horário? Responda este e-mail que a equipe remarca.',
        'sign_off' => 'Até breve,',
        'team' => 'Equipe :firm',
        'hello' => 'Olá, :name.',
        'hello_anonymous' => 'Olá.',
    ],

    'booking' => [
        'subject' => 'Reunião confirmada: Reforma Tributária · :when',
        'preheader' => 'Sua reunião gratuita com :specialist está marcada. O seu Relatório de Impacto já está disponível.',
        'heading' => 'Reunião confirmada',
        'body' => 'Sua reunião gratuita sobre a Reforma Tributária está confirmada.',
        'report_intro' => 'Preparamos o seu Relatório de Impacto, com o resultado do diagnóstico e o simulador 2026–2033. Ele fica disponível por 30 dias.',
        'report_action' => 'Abrir o Relatório de Impacto',
    ],

    'reminder' => [
        'subject' => 'Lembrete: sua reunião sobre a Reforma é :when',
        'preheader' => 'Sua reunião com :specialist está chegando.',
        'heading' => 'Sua reunião está chegando',
        'body' => 'Este é o lembrete da sua reunião sobre a Reforma Tributária.',
        'bring' => 'Leve, se puder, o último faturamento mensal e a lista dos principais clientes e fornecedores.',
        'report_action' => 'Rever o Relatório de Impacto',
    ],

    'referral' => [
        'subject' => 'Indicação da :firm: :company',
        'preheader' => 'A :firm indica a :company para a sua equipe.',
        'heading' => 'Uma indicação da :firm',
        'hello' => 'Olá, equipe :partner.',
        'body' => 'A :firm indica uma empresa que fez o diagnóstico da Reforma Tributária e pode precisar de vocês em :specialty.',
        'company_label' => 'Empresa',
        'covers_label' => 'Onde vocês podem ajudar',
        'message_label' => 'Mensagem de :name',
        'through_firm' => 'O primeiro contato com a empresa é feito pela :firm, que apresenta vocês. Responda este e-mail ou fale com:',
        'phone_label' => 'Telefone',
        'email_label' => 'E-mail',
        'footer_reason' => 'Você recebeu este e-mail porque faz parte da rede de parceiros da :firm.',
        'sectors' => [
            'comercio' => 'Comércio',
            'industria' => 'Indústria',
            'servicos' => 'Serviços',
            'saude' => 'Saúde',
            'educacao' => 'Educação',
            'agro' => 'Agronegócio',
        ],
    ],
];
