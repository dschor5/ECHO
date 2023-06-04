/**
 * Time Module for updating the current time at MCC and the HAB.
 */

$(document).ready(function() {

    // Update time display at 1Hz.
    setTimeout(updateTime, 1000);
});

/**
 * Update time diaplay. 
 */
function updateTime() {

    // Update 
    var currTime = new Date();
    var startTime = MISSION_START.getTime();
    var endTime = MISSION_END.getTime();
    var mccTime = formatTime(null, true);
    $('#time-mcc-value').text(mccTime);   

    // Flag to select HAB format ()
    if(HAB_FORMAT && currTime < endTime) {
        
        // Before the start of the mission
        if(currTime < startTime)
        {
            var habMet = (startTime - currTime) / 1000; // sec
            var day = Math.floor(habMet / SEC_PER_DAY);
            var hrs = Math.floor((habMet - day * SEC_PER_DAY) / 3600);
            var min = Math.floor((habMet - day * SEC_PER_DAY - hrs * 3600) / 60);
            var sec = Math.floor(habMet - day * SEC_PER_DAY - hrs * 3600 - min * 60);

            habDate = 'T-';
            if(day > 0)
            {
                habDate += Math.abs(day) + 'd ';
            } 
            habDate += (Math.abs(hrs)).toString().padStart(2, "0") + ":" + 
                min.toString().padStart(2, "0") + ":" +
                sec.toString().padStart(2, "0");
        }
        // During the mission
        else
        {
            var habMet = (currTime - startTime) / 1000; // sec

            var day = Math.floor(habMet / SEC_PER_DAY);
            var hrs = Math.floor((habMet - day * SEC_PER_DAY) / 3600);
            var min = Math.floor((habMet - day * SEC_PER_DAY - hrs * 3600) / 60);
            var sec = Math.floor(habMet - day * SEC_PER_DAY - hrs * 3600 - min * 60);

            habDate = TIME_DAY + "-" + (day + 1) + " " + 
                        hrs.toString().padStart(2, "0") + ":" + 
                        min.toString().padStart(2, "0") + ":" +
                        sec.toString().padStart(2, "0");
        }
    }
    // After the mission. 
    else {
        habDate = formatTime(null, false);  
    }
    $('#time-hab-value').text(habDate);  
    
    setTimeout(updateTime, 1000);
}

/**
 * Format string to display time. 
 * @param {string} timeStr 
 * @param {boolean} mccOffset 
 * @returns stirng
 */
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
    return dt.getUTCFullYear() + "-" + 
        (dt.getUTCMonth()+1).toString().padStart(2, "0") + "-" + 
        dt.getUTCDate().toString().padStart(2, "0") + " " +
        dt.getUTCHours().toString().padStart(2, "0") + ":" + 
        dt.getUTCMinutes().toString().padStart(2, "0") + ":" + 
        dt.getUTCSeconds().toString().padStart(2, "0");          
}