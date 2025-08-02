inipos=$(php makemove.php init)

echo "$inipos" >>log.txt

echo "$inipos" | tail -n1 > pos.txt

move=1

while true ; do
    echo "*** SENTE ($move) ***"
    res=$(shogi_drop_depth=2 php makemove.php <pos.txt)
    code=$?
    echo "$res"
    echo "$res" |tail -n1 > pos.txt
    #read v
    if [[ "$code" -eq "7" ]] ; then
        echo "Sente (black) wins!"
        echo "sente ($move)" >> log.txt
        break
    fi
    echo "*** GOTE ($move) ***"
    res=$(php makemove.php <pos.txt)
    code=$?
    echo "$res"
    echo "$res" |tail -n1 > pos.txt
    #read v
    if [[ "$code" -eq "7" ]] ; then
        echo "Gote (white) wins!"
        echo "gote ($move)" >> log.txt
        break
    fi
    sleep 1
    move=$((move+1))
done
