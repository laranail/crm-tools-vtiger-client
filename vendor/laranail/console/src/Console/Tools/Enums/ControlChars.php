<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Console\Tools\Enums;

/**
 * The complete C0 control-character set (plus DEL), as a typed primitive — a
 * documented reference for terminal control. Codes are the ECMA-48 / ASCII
 * values; {@see char()} yields the actual byte.
 *
 * Re-derived from the ECMA-48 specification (numeric facts), not copied from any
 * third-party library.
 */
enum ControlChars: int
{
    case Nul = 0;   // null
    case Soh = 1;   // start of heading
    case Stx = 2;   // start of text
    case Etx = 3;   // end of text
    case Eot = 4;   // end of transmission
    case Enq = 5;   // enquiry
    case Ack = 6;   // acknowledge
    case Bel = 7;   // bell (\a)
    case Bs = 8;    // backspace
    case Ht = 9;    // horizontal tab (\t)
    case Lf = 10;   // line feed (\n)
    case Vt = 11;   // vertical tab
    case Ff = 12;   // form feed
    case Cr = 13;   // carriage return (\r)
    case So = 14;   // shift out
    case Si = 15;   // shift in
    case Dle = 16;  // data link escape
    case Dc1 = 17;  // device control 1 (XON)
    case Dc2 = 18;  // device control 2
    case Dc3 = 19;  // device control 3 (XOFF)
    case Dc4 = 20;  // device control 4
    case Nak = 21;  // negative acknowledge
    case Syn = 22;  // synchronous idle
    case Etb = 23;  // end of transmission block
    case Can = 24;  // cancel
    case Em = 25;   // end of medium
    case Sub = 26;  // substitute
    case Esc = 27;  // escape
    case Fs = 28;   // file separator
    case Gs = 29;   // group separator
    case Rs = 30;   // record separator
    case Us = 31;   // unit separator
    case Del = 127; // delete

    /**
     * The control character as a one-byte string.
     */
    public function char(): string
    {
        return chr($this->value);
    }
}
