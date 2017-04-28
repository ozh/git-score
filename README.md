# git-score

*git score* is a script to compute some "scores" for committers in a git repo. Use it for fun or to brag about your involvement in the development of a project.

This script is inspired by [git-score](https://github.com/msparks/git-score), a python script

## Use as a git alias:

Add this to your git config (eg `~/.gitconfig`)

```ini
[alias]
    score = "!php /full/path/to/git-score.php"
```

## Usage

In a repository, type:

```sh
git score
```

This will output something like the following:

```
$ git score
name              commits  delta    (+)    (-)  files
Ozh                  2230  47906  66188  18282    500
Léo Colombaro         145   1038  15438  14400     84
lesterchan             43    553   1366    813     24
Nic Waller             13    322    434    112      5
BestNa.me Labs         12     10     21     11      4
Preovaleo              11     -5     28     33      7
Clayton Daley           9     13     29     16      2
Diftraku                8      0     16     16      8
Audrey                  4     10     21     11      4
```

## License

Do whatever the hell you want to do with it
