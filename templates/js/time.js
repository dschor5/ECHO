$(document).ready(setTimeout(updateTime, 1000));

function updateTime() {
    var dt = new Date();
    dt.setTime(dt.getTime() + TZ_MCC_OFFSET * 1000);

    var mccDate = dt.getUTCFullYear() + "-" + 
        (dt.getUTCMonth()+1).toString().padStart(2, "0") + "-" + 
        dt.getUTCDate().toString().padStart(2, "0") + " " +
        dt.getUTCHours().toString().padStart(2, "0") + ":" + 
        dt.getUTCMinutes().toString().padStart(2, "0") + ":" + 
        dt.getUTCSeconds().toString().padStart(2, "0");     
    $('#time-mcc-value').text(mccDate);   

    dt = new Date();
    var habMet = 0;
    var habDate = "";
    if(HAB_FORMAT) {
        habMet = (dt.getTime() - EPOCH_UTC.getTime() + TZ_HAB_OFFSET * 1000) / 1000; // seconds
        var day = Math.floor(habMet / SEC_PER_DAY);
        var hrs = Math.floor((habMet - day * SEC_PER_DAY) / 3600);
        var min = Math.floor((habMet - day * SEC_PER_DAY - hrs * 3600) / 60);
        var sec = Math.floor(habMet - day * SEC_PER_DAY - hrs * 3600 - min * 60);

        habDate = TIME_DAY + "-" + day + " " + 
                    hrs.toString().padStart(2, "0") + ":" + 
                    min.toString().padStart(2, "0") + ":" +
                    sec.toString().padStart(2, "0");
    }
    else {
        var dt = new Date();
        dt.setTime(dt.getTime() + TZ_HAB_OFFSET * 1000);

        var mccDate = dt.getUTCFullYear() + "-" + 
            (dt.getUTCMonth()+1).toString().padStart(2, "0") + "-" + 
            dt.getUTCDate().toString().padStart(2, "0") + " " +
            dt.getUTCHours().toString().padStart(2, "0") + ":" + 
            dt.getUTCMinutes().toString().padStart(2, "0") + ":" + 
            dt.getUTCSeconds().toString().padStart(2, "0");     
    }
    $('#time-hab-value').text(habDate);  
    
    setTimeout(updateTime, 1000);
}

function formatTime(timeStr, mccOffset=USER_IN_MCC) {
    var dt;
    if(timeStr == null) {
        dt = new Date();
    }
    else {
        dt = new Date(timeStr);
    }
    var offset = (mccOffset) ? TZ_MCC_OFFSET : TZ_HAB_OFFSET;
    var ts = dt.getTime() + offset * 1000; // milliseconds

    // Format MCC date
    dt.setTime(ts);
    return dt.getFullYear() + "-" + 
        (dt.getMonth()+1).toString().padStart(2, "0") + "-" + 
        dt.getDate().toString().padStart(2, "0") + " " +
        dt.getHours().toString().padStart(2, "0") + ":" + 
        dt.getMinutes().toString().padStart(2, "0") + ":" + 
        dt.getSeconds().toString().padStart(2, "0");          
}