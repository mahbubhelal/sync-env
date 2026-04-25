<?php

declare(strict_types=1);

dataset('env_values', [
    [
        <<<'ENV'
            SINGLE_VALUE=single
            ENV,
        0,
    ],
    [
        <<<'ENV'
            IN_SINGLE_QUOTES='single quotes'
            ENV,
        0,
    ],
    [
        <<<'ENV'
            IN_DOUBLE_QUOTES="double quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            NESTED_QUOTES="nested 'single' quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ESCAPED_QUOTES="escaped \"double\" quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            NESTED_SINGLE_QUOTES='nested "double" quotes'
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ESCAPED_DOUBLE_QUOTES="escaped \"double\" quotes with 'single' quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_REFERENCE=${SINGLE_VALUE}_reference
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_IN_SINGLE_QUOTES='${SINGLE_VALUE}_in_quotes'
            ENV,
        0,
    ],
    [
        <<<'ENV'
            MISSING="${_VALUE}_in_quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_IN_DOUBLE_QUOTES="${SINGLE_VALUE}_in_quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_WITH_ESCAPED_QUOTES="escaped \${SINGLE_VALUE}_in_quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_IN_DOUBLE_QUOTES_WITH_ESCAPED_QUOTES="escaped \${SINGLE_VALUE}_in_quotes with \"double\" quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            INVALID_LEADING_SPACE= leadingSpace
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_SPACE_WITHIN_VALUE=leading Space
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_ESCAPED_SINGLE_QUOTES='escaped \'single\' quotes'
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_NESTED_SINGLE_QUOTES_ESCAPED='nested \'single\' quotes with "double" quotes'
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_UNCLOSED_SINGLE_QUOTES='unclosed single quotes
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_ESCAPED_BACKSLASH_IN_SINGLE_QUOTES='escaped backslash at end of line\\
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_QUOTES="mismatched 'quotes'""
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_QUOTES_IN_SINGLE_QUOTES='mismatched "quotes"''
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_VALUE_REFERENCE=${VAl UE}
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_A_VALUE_REFERENCE=${ VAlUE}
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_B_VALUE_REFERENCE=${VAlUE }
            ENV,
        1,
    ],
]);
