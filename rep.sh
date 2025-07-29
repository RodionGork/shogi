echo "grhcks/pppppp/6/6/PPPPPP/SKCHRG b" > pos.txt

while true ; do
    echo "*** SENTE ***"
    res=$(shogi_depth=2 php makemove.php <pos.txt)
    echo "$res"
    echo "$res" |tail -n1 > pos.txt
    read v
    echo "*** GOTE ***"
    res=$(shogi_depth=5 php makemove.php <pos.txt)
    echo "$res"
    echo "$res" |tail -n1 > pos.txt
    read v
done
