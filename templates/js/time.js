$(document).ready(setTimeout(updateTime, 1000));

function updateTime() {
    var dt = new Date();
    var mccMet = dt.getTime() + TZ_OFFSET * 1000; // milliseconds

    // Format MCC date
    dt.setTime(mccMet);
    var mccDate = dt.getUTCFullYear() + "-" + 
                  (dt.getUTCMonth()+1).toString().padStart(2, "0") + "-" + 
                  dt.getUTCDate().toString().padStart(2, "0") + " " +
                  dt.getUTCHours().toString().padStart(2, "0") + ":" + 
                  dt.getUTCMinutes().toString().padStart(2, "0") + ":" + 
                  dt.getUTCSeconds().toString().padStart(2, "0");
    $('#time-mcc-value').text(mccDate);   

    // Format HAB date
    var habMet = (mccMet - EPOCH_UTC.getTime() - TZ_OFFSET * 1000) / 1000; // sec
    var day = Math.floor(habMet / SEC_PER_DAY);
    var hrs = Math.floor((habMet - day * SEC_PER_DAY) / 3600);
    var min = Math.floor((habMet - day * SEC_PER_DAY - hrs * 3600) / 60);
    var sec = Math.floor(habMet - day * SEC_PER_DAY - hrs * 3600 - min * 60);

    var habDate = TIME_DAY + "-" + day + " " + 
                  hrs.toString().padStart(2, "0") + ":" + 
                  min.toString().padStart(2, "0") + ":" +
                  sec.toString().padStart(2, "0");
    $('#time-hab-value').text(habDate);  
    
    setTimeout(updateTime, 1000);
}