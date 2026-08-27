<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Colors\Helpers;

use Simtabi\Pheg\Toolbox\Colors\Palettes\Css3Palette;
use League\ColorExtractor\Palette as LeaguePalette;
use League\ColorExtractor\ColorExtractor as LeagueColorExtractor;

/**
 * Class ColorPicker
 *
 * @package ericpugh\handy-colors
 */
class Picker
{


    //@todo remove duplicate array keys
    protected const AVAILABLE_COLOR_NAMES      = [
        'aliceblue',
        'antiquewhite',
        'aqua',
        'aquamarine',
        'azure', 'beige',
        'bisque',
        'black',
        'blanchedalmond',
        'blue',
        'blueviolet',
        'brown',
        'burlywood',
        'cadetblue',
        'chartreuse',
        'chocolate',
        'coral',
        'cornflowerblue',
        'cornsilk',
        'crimson',
        'cyan',
        'darkblue',
        'darkcyan',
        'darkgoldenrod',
        'darkgray',
        'darkgrey',
        'darkgreen',
        'darkkhaki',
        'darkmagenta',
        'darkolivegreen',
        'darkorange',
        'darkorchid',
        'darkred',
        'darksalmon',
        'darkseagreen',
        'darkslateblue',
        'darkslategray',
        'darkslategrey',
        'darkturquoise',
        'darkviolet',
        'deeppink',
        'deepskyblue',
        'dimgray',
        'dimgrey',
        'dodgerblue',
        'firebrick',
        'floralwhite',
        'forestgreen',
        'fuchsia',
        'gainsboro',
        'ghostwhite',
        'gold',
        'goldenrod',
        'gray',
        'grey',
        'green',
        'greenyellow',
        'honeydew',
        'hotpink',
        'indianred',
        'indigo',
        'ivory',
        'khaki',
        'lavender',
        'lavenderblush',
        'lawngreen',
        'lemonchiffon',
        'lightblue',
        'lightcoral',
        'lightcyan',
        'lightgoldenrodyellow',
        'lightgray',
        'lightgrey',
        'lightgreen',
        'lightpink',
        'lightsalmon',
        'lightseagreen',
        'lightskyblue',
        'lightslategray',
        'lightslategrey',
        'lightsteelblue',
        'lightyellow',
        'lime',
        'limegreen',
        'linen',
        'magenta',
        'maroon',
        'mediumaquamarine',
        'mediumblue',
        'mediumorchid',
        'mediumpurple',
        'mediumseagreen',
        'mediumslateblue',
        'mediumspringgreen',
        'mediumturquoise',
        'mediumvioletred',
        'midnightblue',
        'mintcream',
        'mistyrose',
        'moccasin',
        'navajowhite',
        'navy',
        'oldlace',
        'olive',
        'olivedrab',
        'orange',
        'orangered',
        'orchid',
        'palegoldenrod',
        'palegreen',
        'paleturquoise',
        'palevioletred',
        'papayawhip',
        'peachpuff',
        'peru',
        'pink',
        'plum',
        'powderblue',
        'purple',
        'rebeccapurple',
        'red',
        'rosybrown',
        'royalblue',
        'saddlebrown',
        'salmon',
        'sandybrown',
        'seagreen',
        'seashell',
        'sienna',
        'silver',
        'skyblue',
        'slateblue',
        'slategray',
        'slategrey',
        'snow',
        'springgreen',
        'steelblue',
        'tan',
        'teal',
        'thistle',
        'tomato',
        'turquoise',
        'violet',
        'wheat',
        'white',
        'whitesmoke',
        'yellow',
        'yellowgreen',
        'aliceblue',
        'antiquewhite',
        'aqua',
        'aquamarine',
        'azure', 'beige',
        'bisque',
        'black',
        'blanchedalmond',
        'blue',
        'blueviolet',
        'brown',
        'burlywood',
        'cadetblue',
        'chartreuse',
        'chocolate',
        'coral',
        'cornflowerblue',
        'cornsilk',
        'crimson',
        'cyan',
        'darkblue',
        'darkcyan',
        'darkgoldenrod',
        'darkgray',
        'darkgrey',
        'darkgreen',
        'darkkhaki',
        'darkmagenta',
        'darkolivegreen',
        'darkorange',
        'darkorchid',
        'darkred',
        'darksalmon',
        'darkseagreen',
        'darkslateblue',
        'darkslategray',
        'darkslategrey',
        'darkturquoise',
        'darkviolet',
        'deeppink',
        'deepskyblue',
        'dimgray',
        'dimgrey',
        'dodgerblue',
        'firebrick',
        'floralwhite',
        'forestgreen',
        'fuchsia',
        'gainsboro',
        'ghostwhite',
        'gold',
        'goldenrod',
        'gray',
        'grey',
        'green',
        'greenyellow',
        'honeydew',
        'hotpink',
        'indianred',
        'indigo',
        'ivory',
        'khaki',
        'lavender',
        'lavenderblush',
        'lawngreen',
        'lemonchiffon',
        'lightblue',
        'lightcoral',
        'lightcyan',
        'lightgoldenrodyellow',
        'lightgray',
        'lightgrey',
        'lightgreen',
        'lightpink',
        'lightsalmon',
        'lightseagreen',
        'lightskyblue',
        'lightslategray',
        'lightslategrey',
        'lightsteelblue',
        'lightyellow',
        'lime',
        'limegreen',
        'linen',
        'magenta',
        'maroon',
        'mediumaquamarine',
        'mediumblue',
        'mediumorchid',
        'mediumpurple',
        'mediumseagreen',
        'mediumslateblue',
        'mediumspringgreen',
        'mediumturquoise',
        'mediumvioletred',
        'midnightblue',
        'mintcream',
        'mistyrose',
        'moccasin',
        'navajowhite',
        'navy',
        'oldlace',
        'olive',
        'olivedrab',
        'orange',
        'orangered',
        'orchid',
        'palegoldenrod',
        'palegreen',
        'paleturquoise',
        'palevioletred',
        'papayawhip',
        'peachpuff',
        'peru',
        'pink',
        'plum',
        'powderblue',
        'purple',
        'rebeccapurple',
        'red',
        'rosybrown',
        'royalblue',
        'saddlebrown',
        'salmon',
        'sandybrown',
        'seagreen',
        'seashell',
        'sienna',
        'silver',
        'skyblue',
        'slateblue',
        'slategray',
        'slategrey',
        'snow',
        'springgreen',
        'steelblue',
        'tan',
        'teal',
        'thistle',
        'tomato',
        'turquoise',
        'violet',
        'wheat',
        'white',
        'whitesmoke',
        'yellow',
        'yellowgreen',

        'silver',
        'gray',
        'white',
        'maroon',
        'red',
        'purple',
        'fuchsia',
        'green',
        'lime',
        'olive',
        'yellow',
        'navy',
        'blue',
        'teal',
        'aqua',
        'orange',
        'aliceblue',
        'antiquewhite',
        'aquamarine',
        'azure',
        'beige',
        'bisque',
        'blanchedalmond',
        'blueviolet',
        'brown',
        'burlywood',
        'cadetblue',
        'chartreuse',
        'chocolate',
        'coral',
        'cornflowerblue',
        'cornsilk',
        'crimson',
        'darkblue',
        'darkcyan',
        'darkgoldenrod',
        'darkgray',
        'darkgreen',
        'darkgrey',
        'darkkhaki',
        'darkmagenta',
        'darkolivegreen',
        'darkorange',
        'darkorchid',
        'darkred',
        'darksalmon',
        'darkseagreen',
        'darkslateblue',
        'darkslategray',
        'darkslategrey',
        'darkturquoise',
        'darkviolet',
        'deeppink',
        'deepskyblue',
        'dimgray',
        'dimgrey',
        'dodgerblue',
        'firebrick',
        'floralwhite',
        'forestgreen',
        'gainsboro',
        'ghostwhite',
        'gold',
        'goldenrod',
        'greenyellow',
        'grey',
        'honeydew',
        'hotpink',
        'indianred',
        'indigo',
        'ivory',
        'khaki',
        'lavender',
        'lavenderblush',
        'lawngreen',
        'lemonchiffon',
        'lightblue',
        'lightcoral',
        'lightcyan',
        'lightgoldenrodyellow',
        'lightgray',
        'lightgreen',
        'lightgrey',
        'lightpink',
        'lightsalmon',
        'lightseagreen',
        'lightskyblue',
        'lightslategray',
        'lightslategrey',
        'lightsteelblue',
        'lightyellow',
        'limegreen',
        'linen',
        'mediumaquamarine',
        'mediumblue',
        'mediumorchid',
        'mediumpurple',
        'mediumseagreen',
        'mediumslateblue',
        'mediumspringgreen',
        'mediumturquoise',
        'mediumvioletred',
        'midnightblue',
        'mintcream',
        'mistyrose',
        'moccasin',
        'navajowhite',
        'oldlace',
        'olivedrab',
        'orangered',
        'orchid',
        'palegoldenrod',
        'palegreen',
        'paleturquoise',
        'palevioletred',
        'papayawhip',
        'peachpuff',
        'peru',
        'pink',
        'plum',
        'powderblue',
        'rosybrown',
        'royalblue',
        'saddlebrown',
        'salmon',
        'sandybrown',
        'seagreen',
        'seashell',
        'sienna',
        'skyblue',
        'slateblue',
        'slategray',
        'slategrey',
        'snow',
        'springgreen',
        'steelblue',
        'tan',
        'thistle',
        'tomato',
        'turquoise',
        'violet',
        'wheat',
        'whitesmoke',
        'yellowgreen',
        'rebeccapurple',
    ];


    const CSS_COLOR_NAMES = [
        "AliceBlue",
        "AntiqueWhite",
        "Aqua",
        "Aquamarine",
        "Azure",
        "Beige",
        "Bisque",
        "Black",
        "BlanchedAlmond",
        "Blue",
        "BlueViolet",
        "Brown",
        "BurlyWood",
        "CadetBlue",
        "Chartreuse",
        "Chocolate",
        "Coral",
        "CornflowerBlue",
        "Cornsilk",
        "Crimson",
        "Cyan",
        "DarkBlue",
        "DarkCyan",
        "DarkGoldenRod",
        "DarkGray",
        "DarkGrey",
        "DarkGreen",
        "DarkKhaki",
        "DarkMagenta",
        "DarkOliveGreen",
        "DarkOrange",
        "DarkOrchid",
        "DarkRed",
        "DarkSalmon",
        "DarkSeaGreen",
        "DarkSlateBlue",
        "DarkSlateGray",
        "DarkSlateGrey",
        "DarkTurquoise",
        "DarkViolet",
        "DeepPink",
        "DeepSkyBlue",
        "DimGray",
        "DimGrey",
        "DodgerBlue",
        "FireBrick",
        "FloralWhite",
        "ForestGreen",
        "Fuchsia",
        "Gainsboro",
        "GhostWhite",
        "Gold",
        "GoldenRod",
        "Gray",
        "Grey",
        "Green",
        "GreenYellow",
        "HoneyDew",
        "HotPink",
        "IndianRed",
        "Indigo",
        "Ivory",
        "Khaki",
        "Lavender",
        "LavenderBlush",
        "LawnGreen",
        "LemonChiffon",
        "LightBlue",
        "LightCoral",
        "LightCyan",
        "LightGoldenRodYellow",
        "LightGray",
        "LightGrey",
        "LightGreen",
        "LightPink",
        "LightSalmon",
        "LightSeaGreen",
        "LightSkyBlue",
        "LightSlateGray",
        "LightSlateGrey",
        "LightSteelBlue",
        "LightYellow",
        "Lime",
        "LimeGreen",
        "Linen",
        "Magenta",
        "Maroon",
        "MediumAquaMarine",
        "MediumBlue",
        "MediumOrchid",
        "MediumPurple",
        "MediumSeaGreen",
        "MediumSlateBlue",
        "MediumSpringGreen",
        "MediumTurquoise",
        "MediumVioletRed",
        "MidnightBlue",
        "MintCream",
        "MistyRose",
        "Moccasin",
        "NavajoWhite",
        "Navy",
        "OldLace",
        "Olive",
        "OliveDrab",
        "Orange",
        "OrangeRed",
        "Orchid",
        "PaleGoldenRod",
        "PaleGreen",
        "PaleTurquoise",
        "PaleVioletRed",
        "PapayaWhip",
        "PeachPuff",
        "Peru",
        "Pink",
        "Plum",
        "PowderBlue",
        "Purple",
        "RebeccaPurple",
        "Red",
        "RosyBrown",
        "RoyalBlue",
        "SaddleBrown",
        "Salmon",
        "SandyBrown",
        "SeaGreen",
        "SeaShell",
        "Sienna",
        "Silver",
        "SkyBlue",
        "SlateBlue",
        "SlateGray",
        "SlateGrey",
        "Snow",
        "SpringGreen",
        "SteelBlue",
        "Tan",
        "Teal",
        "Thistle",
        "Tomato",
        "Turquoise",
        "Violet",
        "Wheat",
        "White",
        "WhiteSmoke",
        "Yellow",
        "YellowGreen",
    ];

    /**
     * Color names to hexadecimal representations map
     *
     * @const COLOR_MAP
     */
    public const COLOR_MAP = [
        "aqua" => "00FFFF",
        "aliceblue" => "F0F8FF",
        "antiquewhite" => "FAEBD7",
        "black" => "000000",
        "blue" => "0000FF",
        "cyan" => "00FFFF",
        "darkblue" => "00008B",
        "darkcyan" => "008B8B",
        "darkgreen" => "006400",
        "darkturquoise" => "00CED1",
        "deepskyblue" => "00BFFF",
        "green" => "008000",
        "lime" => "00FF00",
        "mediumblue" => "0000CD",
        "mediumspringgreen" => "00FA9A",
        "navy" => "000080",
        "springgreen" => "00FF7F",
        "teal" => "008080",
        "midnightblue" => "191970",
        "dodgerblue" => "1E90FF",
        "lightseagreen" => "20B2AA",
        "forestgreen" => "228B22",
        "seagreen" => "2E8B57",
        "darkslategray" => "2F4F4F",
        "darkslategrey" => "2F4F4F",
        "limegreen" => "32CD32",
        "mediumseagreen" => "3CB371",
        "turquoise" => "40E0D0",
        "royalblue" => "4169E1",
        "steelblue" => "4682B4",
        "darkslateblue" => "483D8B",
        "mediumturquoise" => "48D1CC",
        "indigo" => "4B0082",
        "darkolivegreen" => "556B2F",
        "cadetblue" => "5F9EA0",
        "cornflowerblue" => "6495ED",
        "mediumaquamarine" => "66CDAA",
        "dimgray" => "696969",
        "dimgrey" => "696969",
        "slateblue" => "6A5ACD",
        "olivedrab" => "6B8E23",
        "slategray" => "708090",
        "slategrey" => "708090",
        "lightslategray" => "778899",
        "lightslategrey" => "778899",
        "mediumslateblue" => "7B68EE",
        "lawngreen" => "7CFC00",
        "aquamarine" => "7FFFD4",
        "chartreuse" => "7FFF00",
        "gray" => "808080",
        "grey" => "808080",
        "maroon" => "800000",
        "olive" => "808000",
        "purple" => "800080",
        "lightskyblue" => "87CEFA",
        "skyblue" => "87CEEB",
        "blueviolet" => "8A2BE2",
        "darkmagenta" => "8B008B",
        "darkred" => "8B0000",
        "saddlebrown" => "8B4513",
        "darkseagreen" => "8FBC8F",
        "lightgreen" => "90EE90",
        "mediumpurple" => "9370DB",
        "darkviolet" => "9400D3",
        "palegreen" => "98FB98",
        "darkorchid" => "9932CC",
        "yellowgreen" => "9ACD32",
        "sienna" => "A0522D",
        "brown" => "A52A2A",
        "darkgray" => "A9A9A9",
        "darkgrey" => "A9A9A9",
        "greenyellow" => "ADFF2F",
        "lightblue" => "ADD8E6",
        "paleturquoise" => "AFEEEE",
        "lightsteelblue" => "B0C4DE",
        "powderblue" => "B0E0E6",
        "firebrick" => "B22222",
        "darkgoldenrod" => "B8860B",
        "mediumorchid" => "BA55D3",
        "rosybrown" => "BC8F8F",
        "darkkhaki" => "BDB76B",
        "silver" => "C0C0C0",
        "mediumvioletred" => "C71585",
        "indianred" => "CD5C5C",
        "peru" => "CD853F",
        "chocolate" => "D2691E",
        "tan" => "D2B48C",
        "lightgray" => "D3D3D3",
        "lightgrey" => "D3D3D3",
        "thistle" => "D8BFD8",
        "goldenrod" => "DAA520",
        "orchid" => "DA70D6",
        "palevioletred" => "DB7093",
        "crimson" => "DC143C",
        "gainsboro" => "DCDCDC",
        "plum" => "DDA0DD",
        "burlywood" => "DEB887",
        "lightcyan" => "E0FFFF",
        "lavender" => "E6E6FA",
        "darksalmon" => "E9967A",
        "palegoldenrod" => "EEE8AA",
        "violet" => "EE82EE",
        "azure" => "F0FFFF",
        "honeydew" => "F0FFF0",
        "khaki" => "F0E68C",
        "lightcoral" => "F08080",
        "sandybrown" => "F4A460",
        "beige" => "F5F5DC",
        "mintcream" => "F5FFFA",
        "wheat" => "F5DEB3",
        "whitesmoke" => "F5F5F5",
        "ghostwhite" => "F8F8FF",
        "lightgoldenrodyellow" => "FAFAD2",
        "linen" => "FAF0E6",
        "salmon" => "FA8072",
        "oldlace" => "FDF5E6",
        "bisque" => "FFE4C4",
        "blanchedalmond" => "FFEBCD",
        "coral" => "FF7F50",
        "cornsilk" => "FFF8DC",
        "darkorange" => "FF8C00",
        "deeppink" => "FF1493",
        "floralwhite" => "FFFAF0",
        "fuchsia" => "FF00FF",
        "gold" => "FFD700",
        "hotpink" => "FF69B4",
        "ivory" => "FFFFF0",
        "lavenderblush" => "FFF0F5",
        "lemonchiffon" => "FFFACD",
        "lightpink" => "FFB6C1",
        "lightsalmon" => "FFA07A",
        "lightyellow" => "FFFFE0",
        "magenta" => "FF00FF",
        "mistyrose" => "FFE4E1",
        "moccasin" => "FFE4B5",
        "navajowhite" => "FFDEAD",
        "orange" => "FFA500",
        "orangered" => "FF4500",
        "papayawhip" => "FFEFD5",
        "peachpuff" => "FFDAB9",
        "pink" => "FFC0CB",
        "red" => "FF0000",
        "seashell" => "FFF5EE",
        "snow" => "FFFAFA",
        "tomato" => "FF6347",
        "white" => "FFFFFF",
        "yellow" => "FFFF00",





        "aliceblue" => [
            "name" => "AliceBlue",
            "hex" => "#F0F8FF"
        ],
        "antiquewhite" => [
            "name" => "AntiqueWhite",
            "hex" => "#FAEBD7"
        ],
        "aqua" => [
            "name" => "Aqua",
            "hex" => "#00FFFF"
        ],
        "aquamarine" => [
            "name" => "Aquamarine",
            "hex" => "#7FFFD4"
        ],
        "azure" => [
            "name" => "Azure",
            "hex" => "#F0FFFF"
        ],
        "beige" => [
            "name" => "Beige",
            "hex" => "#F5F5DC"
        ],
        "bisque" => [
            "name" => "Bisque",
            "hex" => "#FFE4C4"
        ],
        "black" => [
            "name" => "Black",
            "hex" => "#000000"
        ],
        "blanchedalmond" => [
            "name" => "BlanchedAlmond",
            "hex" => "#FFEBCD"
        ],
        "blue" => [
            "name" => "Blue",
            "hex" => "#0000FF"
        ],
        "blueviolet" => [
            "name" => "BlueViolet",
            "hex" => "#8A2BE2"
        ],
        "brown" => [
            "name" => "Brown",
            "hex" => "#A52A2A"
        ],
        "burlywood" => [
            "name" => "BurlyWood",
            "hex" => "#DEB887"
        ],
        "cadetblue" => [
            "name" => "CadetBlue",
            "hex" => "#5F9EA0"
        ],
        "chartreuse" => [
            "name" => "Chartreuse",
            "hex" => "#7FFF00"
        ],
        "chocolate" => [
            "name" => "Chocolate",
            "hex" => "#D2691E"
        ],
        "coral" => [
            "name" => "Coral",
            "hex" => "#FF7F50"
        ],
        "cornflowerblue" => [
            "name" => "CornflowerBlue",
            "hex" => "#6495ED"
        ],
        "cornsilk" => [
            "name" => "Cornsilk",
            "hex" => "#FFF8DC"
        ],
        "crimson" => [
            "name" => "Crimson",
            "hex" => "#DC143C"
        ],
        "cyan" => [
            "name" => "Cyan",
            "hex" => "#00FFFF"
        ],
        "darkblue" => [
            "name" => "DarkBlue",
            "hex" => "#00008B"
        ],
        "darkcyan" => [
            "name" => "DarkCyan",
            "hex" => "#008B8B"
        ],
        "darkgoldenrod" => [
            "name" => "DarkGoldenRod",
            "hex" => "#B8860B"
        ],
        "darkgray" => [
            "name" => "DarkGray",
            "hex" => "#A9A9A9"
        ],
        "darkgrey" => [
            "name" => "DarkGrey",
            "hex" => "#A9A9A9"
        ],
        "darkgreen" => [
            "name" => "DarkGreen",
            "hex" => "#006400"
        ],
        "darkkhaki" => [
            "name" => "DarkKhaki",
            "hex" => "#BDB76B"
        ],
        "darkmagenta" => [
            "name" => "DarkMagenta",
            "hex" => "#8B008B"
        ],
        "darkolivegreen" => [
            "name" => "DarkOliveGreen",
            "hex" => "#556B2F"
        ],
        "darkorange" => [
            "name" => "DarkOrange",
            "hex" => "#FF8C00"
        ],
        "darkorchid" => [
            "name" => "DarkOrchid",
            "hex" => "#9932CC"
        ],
        "darkred" => [
            "name" => "DarkRed",
            "hex" => "#8B0000"
        ],
        "darksalmon" => [
            "name" => "DarkSalmon",
            "hex" => "#E9967A"
        ],
        "darkseagreen" => [
            "name" => "DarkSeaGreen",
            "hex" => "#8FBC8F"
        ],
        "darkslateblue" => [
            "name" => "DarkSlateBlue",
            "hex" => "#483D8B"
        ],
        "darkslategray" => [
            "name" => "DarkSlateGray",
            "hex" => "#2F4F4F"
        ],
        "darkslategrey" => [
            "name" => "DarkSlateGrey",
            "hex" => "#2F4F4F"
        ],
        "darkturquoise" => [
            "name" => "DarkTurquoise",
            "hex" => "#00CED1"
        ],
        "darkviolet" => [
            "name" => "DarkViolet",
            "hex" => "#9400D3"
        ],
        "deeppink" => [
            "name" => "DeepPink",
            "hex" => "#FF1493"
        ],
        "deepskyblue" => [
            "name" => "DeepSkyBlue",
            "hex" => "#00BFFF"
        ],
        "dimgray" => [
            "name" => "DimGray",
            "hex" => "#696969"
        ],
        "dimgrey" => [
            "name" => "DimGrey",
            "hex" => "#696969"
        ],
        "dodgerblue" => [
            "name" => "DodgerBlue",
            "hex" => "#1E90FF"
        ],
        "firebrick" => [
            "name" => "FireBrick",
            "hex" => "#B22222"
        ],
        "floralwhite" => [
            "name" => "FloralWhite",
            "hex" => "#FFFAF0"
        ],
        "forestgreen" => [
            "name" => "ForestGreen",
            "hex" => "#228B22"
        ],
        "fuchsia" => [
            "name" => "Fuchsia",
            "hex" => "#FF00FF"
        ],
        "gainsboro" => [
            "name" => "Gainsboro",
            "hex" => "#DCDCDC"
        ],
        "ghostwhite" => [
            "name" => "GhostWhite",
            "hex" => "#F8F8FF"
        ],
        "gold" => [
            "name" => "Gold",
            "hex" => "#FFD700"
        ],
        "goldenrod" => [
            "name" => "GoldenRod",
            "hex" => "#DAA520"
        ],
        "gray" => [
            "name" => "Gray",
            "hex" => "#808080"
        ],
        "grey" => [
            "name" => "Grey",
            "hex" => "#808080"
        ],
        "green" => [
            "name" => "Green",
            "hex" => "#008000"
        ],
        "greenyellow" => [
            "name" => "GreenYellow",
            "hex" => "#ADFF2F"
        ],
        "honeydew" => [
            "name" => "HoneyDew",
            "hex" => "#F0FFF0"
        ],
        "hotpink" => [
            "name" => "HotPink",
            "hex" => "#FF69B4"
        ],
        "indianred" => [
            "name" => "IndianRed",
            "hex" => "#CD5C5C"
        ],
        "indigo" => [
            "name" => "Indigo",
            "hex" => "#4B0082"
        ],
        "ivory" => [
            "name" => "Ivory",
            "hex" => "#FFFFF0"
        ],
        "khaki" => [
            "name" => "Khaki",
            "hex" => "#F0E68C"
        ],
        "lavender" => [
            "name" => "Lavender",
            "hex" => "#E6E6FA"
        ],
        "lavenderblush" => [
            "name" => "LavenderBlush",
            "hex" => "#FFF0F5"
        ],
        "lawngreen" => [
            "name" => "LawnGreen",
            "hex" => "#7CFC00"
        ],
        "lemonchiffon" => [
            "name" => "LemonChiffon",
            "hex" => "#FFFACD"
        ],
        "lightblue" => [
            "name" => "LightBlue",
            "hex" => "#ADD8E6"
        ],
        "lightcoral" => [
            "name" => "LightCoral",
            "hex" => "#F08080"
        ],
        "lightcyan" => [
            "name" => "LightCyan",
            "hex" => "#E0FFFF"
        ],
        "lightgoldenrodyellow" => [
            "name" => "LightGoldenRodYellow",
            "hex" => "#FAFAD2"
        ],
        "lightgray" => [
            "name" => "LightGray",
            "hex" => "#D3D3D3"
        ],
        "lightgrey" => [
            "name" => "LightGrey",
            "hex" => "#D3D3D3"
        ],
        "lightgreen" => [
            "name" => "LightGreen",
            "hex" => "#90EE90"
        ],
        "lightpink" => [
            "name" => "LightPink",
            "hex" => "#FFB6C1"
        ],
        "lightsalmon" => [
            "name" => "LightSalmon",
            "hex" => "#FFA07A"
        ],
        "lightseagreen" => [
            "name" => "LightSeaGreen",
            "hex" => "#20B2AA"
        ],
        "lightskyblue" => [
            "name" => "LightSkyBlue",
            "hex" => "#87CEFA"
        ],
        "lightslategray" => [
            "name" => "LightSlateGray",
            "hex" => "#778899"
        ],
        "lightslategrey" => [
            "name" => "LightSlateGrey",
            "hex" => "#778899"
        ],
        "lightsteelblue" => [
            "name" => "LightSteelBlue",
            "hex" => "#B0C4DE"
        ],
        "lightyellow" => [
            "name" => "LightYellow",
            "hex" => "#FFFFE0"
        ],
        "lime" => [
            "name" => "Lime",
            "hex" => "#00FF00"
        ],
        "limegreen" => [
            "name" => "LimeGreen",
            "hex" => "#32CD32"
        ],
        "linen" => [
            "name" => "Linen",
            "hex" => "#FAF0E6"
        ],
        "magenta" => [
            "name" => "Magenta",
            "hex" => "#FF00FF"
        ],
        "maroon" => [
            "name" => "Maroon",
            "hex" => "#800000"
        ],
        "mediumaquamarine" => [
            "name" => "MediumAquaMarine",
            "hex" => "#66CDAA"
        ],
        "mediumblue" => [
            "name" => "MediumBlue",
            "hex" => "#0000CD"
        ],
        "mediumorchid" => [
            "name" => "MediumOrchid",
            "hex" => "#BA55D3"
        ],
        "mediumpurple" => [
            "name" => "MediumPurple",
            "hex" => "#9370DB"
        ],
        "mediumseagreen" => [
            "name" => "MediumSeaGreen",
            "hex" => "#3CB371"
        ],
        "mediumslateblue" => [
            "name" => "MediumSlateBlue",
            "hex" => "#7B68EE"
        ],
        "mediumspringgreen" => [
            "name" => "MediumSpringGreen",
            "hex" => "#00FA9A"
        ],
        "mediumturquoise" => [
            "name" => "MediumTurquoise",
            "hex" => "#48D1CC"
        ],
        "mediumvioletred" => [
            "name" => "MediumVioletRed",
            "hex" => "#C71585"
        ],
        "midnightblue" => [
            "name" => "MidnightBlue",
            "hex" => "#191970"
        ],
        "mintcream" => [
            "name" => "MintCream",
            "hex" => "#F5FFFA"
        ],
        "mistyrose" => [
            "name" => "MistyRose",
            "hex" => "#FFE4E1"
        ],
        "moccasin" => [
            "name" => "Moccasin",
            "hex" => "#FFE4B5"
        ],
        "navajowhite" => [
            "name" => "NavajoWhite",
            "hex" => "#FFDEAD"
        ],
        "navy" => [
            "name" => "Navy",
            "hex" => "#000080"
        ],
        "oldlace" => [
            "name" => "OldLace",
            "hex" => "#FDF5E6"
        ],
        "olive" => [
            "name" => "Olive",
            "hex" => "#808000"
        ],
        "olivedrab" => [
            "name" => "OliveDrab",
            "hex" => "#6B8E23"
        ],
        "orange" => [
            "name" => "Orange",
            "hex" => "#FFA500"
        ],
        "orangered" => [
            "name" => "OrangeRed",
            "hex" => "#FF4500"
        ],
        "orchid" => [
            "name" => "Orchid",
            "hex" => "#DA70D6"
        ],
        "palegoldenrod" => [
            "name" => "PaleGoldenRod",
            "hex" => "#EEE8AA"
        ],
        "palegreen" => [
            "name" => "PaleGreen",
            "hex" => "#98FB98"
        ],
        "paleturquoise" => [
            "name" => "PaleTurquoise",
            "hex" => "#AFEEEE"
        ],
        "palevioletred" => [
            "name" => "PaleVioletRed",
            "hex" => "#DB7093"
        ],
        "papayawhip" => [
            "name" => "PapayaWhip",
            "hex" => "#FFEFD5"
        ],
        "peachpuff" => [
            "name" => "PeachPuff",
            "hex" => "#FFDAB9"
        ],
        "peru" => [
            "name" => "Peru",
            "hex" => "#CD853F"
        ],
        "pink" => [
            "name" => "Pink",
            "hex" => "#FFC0CB"
        ],
        "plum" => [
            "name" => "Plum",
            "hex" => "#DDA0DD"
        ],
        "powderblue" => [
            "name" => "PowderBlue",
            "hex" => "#B0E0E6"
        ],
        "purple" => [
            "name" => "Purple",
            "hex" => "#800080"
        ],
        "rebeccapurple" => [
            "name" => "RebeccaPurple",
            "hex" => "#663399"
        ],
        "red" => [
            "name" => "Red",
            "hex" => "#FF0000"
        ],
        "rosybrown" => [
            "name" => "RosyBrown",
            "hex" => "#BC8F8F"
        ],
        "royalblue" => [
            "name" => "RoyalBlue",
            "hex" => "#4169E1"
        ],
        "saddlebrown" => [
            "name" => "SaddleBrown",
            "hex" => "#8B4513"
        ],
        "salmon" => [
            "name" => "Salmon",
            "hex" => "#FA8072"
        ],
        "sandybrown" => [
            "name" => "SandyBrown",
            "hex" => "#F4A460"
        ],
        "seagreen" => [
            "name" => "SeaGreen",
            "hex" => "#2E8B57"
        ],
        "seashell" => [
            "name" => "SeaShell",
            "hex" => "#FFF5EE"
        ],
        "sienna" => [
            "name" => "Sienna",
            "hex" => "#A0522D"
        ],
        "silver" => [
            "name" => "Silver",
            "hex" => "#C0C0C0"
        ],
        "skyblue" => [
            "name" => "SkyBlue",
            "hex" => "#87CEEB"
        ],
        "slateblue" => [
            "name" => "SlateBlue",
            "hex" => "#6A5ACD"
        ],
        "slategray" => [
            "name" => "SlateGray",
            "hex" => "#708090"
        ],
        "slategrey" => [
            "name" => "SlateGrey",
            "hex" => "#708090"
        ],
        "snow" => [
            "name" => "Snow",
            "hex" => "#FFFAFA"
        ],
        "springgreen" => [
            "name" => "SpringGreen",
            "hex" => "#00FF7F"
        ],
        "steelblue" => [
            "name" => "SteelBlue",
            "hex" => "#4682B4"
        ],
        "tan" => [
            "name" => "Tan",
            "hex" => "#D2B48C"
        ],
        "teal" => [
            "name" => "Teal",
            "hex" => "#008080"
        ],
        "thistle" => [
            "name" => "Thistle",
            "hex" => "#D8BFD8"
        ],
        "tomato" => [
            "name" => "Tomato",
            "hex" => "#FF6347"
        ],
        "turquoise" => [
            "name" => "Turquoise",
            "hex" => "#40E0D0"
        ],
        "violet" => [
            "name" => "Violet",
            "hex" => "#EE82EE"
        ],
        "wheat" => [
            "name" => "Wheat",
            "hex" => "#F5DEB3"
        ],
        "white" => [
            "name" => "White",
            "hex" => "#FFFFFF"
        ],
        "whitesmoke" => [
            "name" => "WhiteSmoke",
            "hex" => "#F5F5F5"
        ],
        "yellow" => [
            "name" => "Yellow",
            "hex" => "#FFFF00"
        ],
        "yellowgreen" => [
            "name" => "YellowGreen",
            "hex" => "#9ACD32"
        ],
    ];


    /**
     * @var array
     */
    public array $palette = [];

    /**
     * ColorPicker constructor.
     *
     * @param array $palette
     */
    public function __construct(array $palette = [])
    {
        if (empty($palette)) {
            // Set a default color palette.
            $palette = Css3Palette::getColors();
        }
        $this->setPalette($palette);
    }

    public static function invoke($palette = []): self
    {
        return new self($palette);
    }

    /**
     * Get color palette data.
     */
    public function getPalette(): array
    {
        return $this->palette;
    }

    /**
     * Set color palette data.
     *
     * @param array $palette
     */
    public function setPalette(array $palette)
    {
        $this->palette = $palette;
    }

    /**
     * Generate a color palette from a file.
     *
     * @param string $filename
     * @param int $num_colors
     * @param int|null $background_color
     *   Crop the image to remove as much bg color as possible.
     */
    public function setPaletteFromFile($filename, $num_colors = 4, $crop_background_color = null)
    {
        $image = $this->getImage($filename);
        if (!is_null($crop_background_color)) {
            $image = imagecropauto($image, IMG_CROP_THRESHOLD, 10, $crop_background_color);
        }

        $palette         = LeaguePalette::fromGD($image);
        imagedestroy($image);
        $extractor       = new LeagueColorExtractor($palette);
        $extractedColors = $extractor->extract($num_colors);
        $colors          = [];
        foreach ($extractedColors as $color) {
            $hex          = self::fromIntToHex($color);
            $colors[$hex] = ['hex' => $hex];
        }
        $this->palette = $colors;
    }

    /**
     * Get a GD Image from file.
     *
     * @param string $filename
     *
     * @return resource|false
     */
    private function getImage($filename)
    {
        return imagecreatefromstring(file_get_contents($filename));
    }

    /**
     * @return int
     */
    public function countColorPalette()
    {
        return count($this->palette);
    }

    /**
     * @param int $limit = NULL
     *
     * @return array
     */
    public function getMostUsedColors($limit = null)
    {
        return array_slice($this->palette, 0, $limit, true);
    }

    /**
     * @param string $hex
     *
     * @return int
     */
    public function fromHexToInt($hex)
    {
        return hexdec(ltrim($hex, '#'));
    }

    /**
     * @param int  $color
     * @param bool $prepend_hash = true
     *
     * @return string
     */
    public function fromIntToHex($color, $prepend_hash = true)
    {
        return ($prepend_hash ? '#' : '') . sprintf('%06X', $color);
    }

    /**
     * @param array $colors
     *   An array of hex colors
     *
     * @return array
     */
    public function hexColorsToInt(array $colors)
    {
        return array_map(array($this, 'fromHexToInt'), $colors);
    }

    /**
     * Get color name from given hex color.
     *
     * @param string $hex
     *   The hex color code.
     *
     * @return string
     *   The name of a color as string
     */
    public function getColorName($hex)
    {
        $hex = strtolower($hex);
        if (isset($this->palette[$hex]) && isset($this->palette[$hex]['name'])) {
            return $this->palette[$hex]['name'];
        }
        return '';
    }

    /**
     * Get color family from given hex color.
     *
     * @param string $hex
     *   The hex color code.
     *
     * @return string
     *   The name of a color as string
     */
    public function getColorFamily($hex)
    {
        $hex = strtolower($hex);
        if (isset($this->palette[$hex]) && isset($this->palette[$hex]['family'])) {
            return $this->palette[$hex]['family'];
        }
        return '';
    }

    /**
     * Find the the closest matching color in the current color palette
     *
     * @param \Simtabi\Pheg\Toolbox\Colors\Color $color
     *   A color object
     *
     * @return string
     *   The closest color as a hex
     */
    public function closestColor(Color $color)
    {
        // Convert the color palette to an array of Int color values.
        $palette           = array_keys($this->palette);
        $searchablePalette = $this->hexColorsToInt($palette);
        // Get the index in the search palette of the closest matching color.
        $matchIndex        = $this->getClosestMatch($color, $searchablePalette);
        return self::fromIntToHex($searchablePalette[$matchIndex]);
    }

    /**
     * Get the closest matching the given color from the an array of colors
     *
     * @param Color $color
     * @param array|PaletteInterface[] $palette
     *   array of integers or Color objects
     *
     * @return mixed the array key of the matched color
     */
    public function getClosestMatch(Color $color, $palette)
    {
        $matchDist = 10000;
        $matchKey  = null;
        foreach ($palette as $key => $item) {
            if (false === ($item instanceof Color)) {
                $item = new Color($item);
            }
            $dist = $color->getDistanceLabFrom($item);
            if ($dist < $matchDist) {
                $matchDist = $dist;
                $matchKey  = $key;
            }
        }

        return $matchKey;
    }


    /**
     * toHex
     *
     * Lookup hexadecimal representation using color name
     *
     * @param string $name
     * @return string
     */
    public function colorNameToHex (string $name)
    {
        return self::COLOR_MAP[str_replace(' ', '', strtolower($name))]["hex"] ?? "";
    }

    /**
     * fromHex
     *
     * Reverse lookup color names using hexadecimal representation
     *
     * @param string $hex
     * @return string
     */
    public function colorNameFromHex (string $hex)
    {
        return array_search(strtoupper($hex), self::COLOR_MAP) ?? '';
    }

    /**
     * toRGB
     *
     * Lookup rgb representation using color name
     *
     * @param string $color
     * @param bool $stringify
     * @return string
     */
    public function colorNameToRGB (string $color, bool $stringify = false)
    {
        $hex = $this->colorNameToHex($color);
        if ($hex == '') {
            return '';
        }
        $red   = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue  = hexdec(substr($hex, 4, 2));
        $rgb   = [$red, $green, $blue];
        if (!$stringify) {
            return $rgb;
        }
        return '(' . implode(',', $rgb) . ')';
    }
}
